<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Salon\CreateSalon;
use App\Enums\BookingStatus;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PromotionMechanic;
use App\Enums\PromotionStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
| booking-breaker against "Primera visita" (step 6.7 d), one request at a
| time. The races live in tests/Concurrency/FirstVisitRaceTest.php.
|
| Estudio Luz: Corte S/ 35, 20 % off on the first visit (S/ 28). Ana works
| Wednesday to Friday, 9 to 18. "Now" is Wednesday 2026-10-07 08:00 (Lima).
*/

/**
 * @param  array<string, mixed>  $with
 */
function fvbPromo(array $with = []): Promotion
{
    $promotion = new Promotion;
    $promotion->forceFill([
        'salon_id' => test()->salon->id,
        'mechanic' => PromotionMechanic::Discount,
        'card' => 'first_visit',
        'name' => '20 % menos en Corte en tu primera visita',
        'status' => PromotionStatus::Published,
        'starts_on' => '2026-10-05',
        'ends_on' => '2026-10-31',
        'percent' => 20,
        'new_clients_only' => true,
        'share_code' => Str::lower(Str::random(10)),
        ...$with,
    ])->save();
    $promotion->services()->sync([test()->cut->id]);

    return $promotion;
}

function fvbAt(string $time, string $day = '2026-10-07'): CarbonImmutable
{
    return CarbonImmutable::parse("{$day} {$time}", 'America/Lima');
}

/**
 * @param  array<string, mixed>  $with
 */
function fvbBook(User $user, string $time, string $day = '2026-10-07', array $with = []): TestResponse
{
    return test()->actingAs($user)->post(route('salons.booking.store', test()->salon), [
        'services' => [['id' => test()->cut->id]],
        'start' => fvbAt($time, $day)->getTimestamp(),
        ...$with,
    ]);
}

/**
 * @param  array<string, mixed>  $with
 */
function fvbTeamBook(string $time, array $with): TestResponse
{
    return test()->actingAs(test()->owner)->post(route('agenda.bookings.store'), [
        'services' => [['id' => test()->cut->id]],
        'start' => fvbAt($time)->getTimestamp(),
        ...$with,
    ]);
}

/**
 * A visit that already happened (last week), written straight to the table.
 */
function fvbPastVisit(SalonClient $client, Staff $staff): Booking
{
    $booking = new Booking;
    $booking->forceFill([
        'salon_id' => $client->salon_id,
        'code' => Str::upper(Str::random(8)),
        'salon_client_id' => $client->id,
        'staff_id' => $staff->id,
        'status' => BookingStatus::Completed,
        'source' => 'staff',
        'starts_at' => fvbAt('10:00', '2026-09-30')->utc(),
        'ends_at' => fvbAt('10:30', '2026-09-30')->utc(),
        'price_cents' => 3500,
    ])->save();

    return $booking;
}

function fvbLatest(): Booking
{
    return Booking::withoutGlobalScope('salon')->latest('id')->firstOrFail();
}

function fvbWithPromotion(): int
{
    return Booking::withoutGlobalScope('salon')
        ->whereNotNull('promotion_id')
        ->whereNotIn('status', [BookingStatus::Cancelled, BookingStatus::Expired])
        ->where(fn ($live) => $live->where('status', '!=', BookingStatus::Pending)->orWhereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))
        ->count();
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(fvbAt('08:00'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0, 'min_lead_minutes' => 0])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach([$this->cut->id]);
    $this->maria = User::factory()->create();
});

describe('a deposit confirmed after its hold ran out', function () {
    /*
    | The hold of her first booking ran out, so it stopped counting: her
    | next booking is a first visit again and takes the price. If the team
    | then confirms the old one, she ends with two "first visits".
    | ChangeBookingStatus::confirm asks ApplyPromotion::stillHasRoomFor,
    | which only looked at places and "one per client".
    */
    it('is refused at the promotion price when she booked again meanwhile', function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'hold_minutes' => 30])->save();
        fvbPromo();

        fvbBook($this->maria, '10:00')->assertSessionHasNoErrors();
        $first = fvbLatest();
        expect($first->price_cents)->toBe(2800)->and($first->status)->toBe(BookingStatus::Pending);

        $this->travel(31)->minutes();
        fvbBook($this->maria, '12:00')->assertSessionHasNoErrors();
        expect(fvbLatest()->price_cents)->toBe(2800);

        expect(fn () => app(ChangeBookingStatus::class)->confirm($first->refresh(), $this->owner))
            ->toThrow(BookingException::class);

        expect($first->refresh()->status)->toBe(BookingStatus::Pending)
            ->and(fvbWithPromotion())->toBe(1);
    });

    it('is still confirmed when she has no other booking', function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'hold_minutes' => 30])->save();
        fvbPromo();

        fvbBook($this->maria, '10:00')->assertSessionHasNoErrors();
        $first = fvbLatest();
        $this->travel(31)->minutes();

        expect(app(ChangeBookingStatus::class)->confirm($first, $this->owner)->status)->toBe(BookingStatus::Confirmed);
    });

    it('lets a newcomer whose only hold ran out take the price again', function () {
        $this->salon->forceFill(['deposit_percent' => 50, 'hold_minutes' => 30])->save();
        fvbPromo();

        fvbBook($this->maria, '10:00')->assertSessionHasNoErrors();
        $this->travel(31)->minutes();
        fvbBook($this->maria, '12:00')->assertSessionHasNoErrors();

        expect(fvbLatest()->price_cents)->toBe(2800);
    });
});

describe('the team books', function () {
    it('at the normal price for someone who already came, even with the promotion ticked', function () {
        fvbPromo();
        $known = SalonClient::factory()->for($this->salon)->create(['name' => 'Rosa']);
        fvbPastVisit($known, $this->staff);

        fvbTeamBook('10:00', ['client_id' => $known->id, 'with_promotion' => true])->assertSessionHasNoErrors();

        expect(fvbLatest()->price_cents)->toBe(3500)->and(fvbLatest()->promotion_id)->toBeNull();
    });

    it('at the promotion price for someone new, typed by name and phone', function () {
        fvbPromo();

        fvbTeamBook('10:00', ['client_name' => 'Rosa Nueva', 'client_phone' => '987654321'])->assertSessionHasNoErrors();
        expect(fvbLatest()->price_cents)->toBe(2800);

        // The same phone is the same person: the second is not a first visit.
        fvbTeamBook('12:00', ['client_name' => 'Rosa Nueva', 'client_phone' => '987654321'])->assertSessionHasNoErrors();
        expect(fvbLatest()->price_cents)->toBe(3500)->and(fvbWithPromotion())->toBe(1);
    });
});

describe('moving a first-visit booking', function () {
    it('keeps its price, and the next booking is still not a first visit (client and team)', function () {
        fvbPromo();
        fvbBook($this->maria, '10:00', '2026-10-09')->assertSessionHasNoErrors();
        $booking = fvbLatest();

        $this->actingAs($this->maria)->post(route('bookings.move', $booking->code), ['start' => fvbAt('12:00', '2026-10-09')->getTimestamp()])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), ['start' => fvbAt('15:00', '2026-10-09')->getTimestamp()])
            ->assertSessionHasNoErrors();

        $booking->refresh();
        expect($booking->starts_at->equalTo(fvbAt('15:00', '2026-10-09')))->toBeTrue()
            ->and($booking->price_cents)->toBe(2800)
            ->and($booking->promotion_cents)->toBe(700);

        fvbBook($this->maria, '10:00', '2026-10-09')->assertSessionHasNoErrors();
        expect(fvbLatest()->price_cents)->toBe(3500)->and(fvbWithPromotion())->toBe(1);
    });
});

describe('what counts as having come', function () {
    it('ignores bookings at another salon', function () {
        fvbPromo();
        $other = Salon::factory()->create();
        $there = SalonClient::factory()->for($other)->create(['user_id' => $this->maria->id]);
        fvbPastVisit($there, Staff::factory()->for($other)->create());

        fvbBook($this->maria, '10:00')->assertSessionHasNoErrors();

        expect(fvbLatest()->price_cents)->toBe(2800);
    });

    it('counts a record that only has a booking still ahead with the team', function () {
        fvbPromo();
        fvbTeamBook('15:00', ['client_name' => 'María', 'client_phone' => '911222333', 'with_promotion' => false])->assertSessionHasNoErrors();
        $client = SalonClient::withoutGlobalScope('salon')->latest('id')->firstOrFail();

        fvbTeamBook('10:00', ['client_id' => $client->id])->assertSessionHasNoErrors();

        expect(fvbLatest()->price_cents)->toBe(3500);
    });
});

describe('places and first visit together', function () {
    it('gives the single place to the first newcomer and the normal price to the next', function () {
        $promotion = fvbPromo(['quota' => 1]);
        $luis = User::factory()->create();

        fvbBook($this->maria, '10:00')->assertSessionHasNoErrors();
        fvbBook($luis, '11:00')->assertSessionHasNoErrors();

        expect(fvbLatest()->price_cents)->toBe(3500)
            ->and(Booking::withoutGlobalScope('salon')->where('promotion_id', $promotion->id)->count())->toBe(1);
    });

    it('does not spend a place on someone who already came', function () {
        $promotion = fvbPromo(['quota' => 1]);
        fvbBook($this->maria, '10:00')->assertSessionHasNoErrors();
        Booking::withoutGlobalScope('salon')->sole()->forceFill(['status' => BookingStatus::Completed, 'promotion_id' => null, 'price_cents' => 3500])->save();

        fvbBook($this->maria, '12:00')->assertSessionHasNoErrors();
        expect(fvbLatest()->price_cents)->toBe(3500);

        fvbBook(User::factory()->create(), '13:00')->assertSessionHasNoErrors();
        expect(fvbLatest()->promotion_id)->toBe($promotion->id);
    });
});
