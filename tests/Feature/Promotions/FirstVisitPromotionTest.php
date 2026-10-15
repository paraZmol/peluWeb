<?php

use App\Actions\Salon\CreateSalon;
use App\Enums\BookingStatus;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PromotionMechanic;
use App\Enums\PromotionStatus;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
| Step 6.7 (d): "Primera visita", a discount only for someone who never had
| a booking at the salon. Estudio Luz: Corte S/ 35; Ana works Wednesday to
| Friday, 9 to 18. "Now" is Wednesday 2026-10-07 08:00 in Lima.
*/

function firstVisitPromo(): Promotion
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
    ])->save();
    $promotion->services()->sync([test()->cut->id]);

    return $promotion;
}

/**
 * @param  array<string, mixed>  $with
 */
function firstVisitBook(User $user, string $time, array $with = []): TestResponse
{
    return test()->actingAs($user)->post(route('salons.booking.store', test()->salon), [
        'services' => [['id' => test()->cut->id]],
        'start' => CarbonImmutable::parse("2026-10-07 {$time}", 'America/Lima')->getTimestamp(),
        ...$with,
    ]);
}

function firstVisitLatestPrice(): int
{
    return Booking::withoutGlobalScope('salon')->latest('id')->firstOrFail()->price_cents;
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0, 'min_lead_minutes' => 0])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->staff->services()->attach([$this->cut->id]);
    $this->maria = User::factory()->create();
});

it('gives the first booking the price, and never a second one', function () {
    firstVisitPromo();

    firstVisitBook($this->maria, '10:00')->assertSessionHasNoErrors();
    expect(firstVisitLatestPrice())->toBe(2800);

    // Still ahead, but already hers: the next one is not a first visit.
    firstVisitBook($this->maria, '12:00')->assertSessionHasNoErrors();
    expect(firstVisitLatestPrice())->toBe(3500);
});

it('counts a missed or a past visit, but not a cancelled one or one whose hold ran out', function (BookingStatus $status, bool $isStillNew) {
    firstVisitBook($this->maria, '10:00')->assertSessionHasNoErrors();
    Booking::withoutGlobalScope('salon')->sole()->forceFill(['status' => $status])->save();
    firstVisitPromo();

    firstVisitBook($this->maria, '12:00')->assertSessionHasNoErrors();

    expect(firstVisitLatestPrice())->toBe($isStillNew ? 2800 : 3500);
})->with([
    'completed' => [BookingStatus::Completed, false],
    'no show' => [BookingStatus::NoShow, false],
    'cancelled' => [BookingStatus::Cancelled, true],
    'expired' => [BookingStatus::Expired, true],
]);

it('knows someone who came by phone when they book online with the same number', function () {
    firstVisitPromo();
    $desk = SalonClient::factory()->for($this->salon)->create(['name' => 'María', 'phone' => '987654321']);
    $past = firstVisitBook($this->maria, '10:00');
    $past->assertSessionHasNoErrors();
    Booking::withoutGlobalScope('salon')->sole()->forceFill(['salon_client_id' => $desk->id, 'status' => BookingStatus::Completed])->save();
    SalonClient::withoutGlobalScope('salon')->where('user_id', $this->maria->id)->update(['phone' => '987654321']);

    firstVisitBook($this->maria, '12:00')->assertSessionHasNoErrors();

    expect(firstVisitLatestPrice())->toBe(3500);
});

it('marks the times for someone new, and none for someone who already came', function () {
    firstVisitPromo();
    $marked = fn () => collect($this->getJson(route('salons.booking.availability', [
        'salon' => $this->salon->slug, 'services' => [['id' => $this->cut->id]], 'date' => '2026-10-08',
    ]))->assertOk()->json('slots'))->whereNotNull('promotion')->count();

    $this->actingAs($this->maria);
    expect($marked())->toBeGreaterThan(0);

    firstVisitBook($this->maria, '10:00')->assertSessionHasNoErrors();

    expect($marked())->toBe(0);
});

it('books nothing when someone who already came confirms the promotion price', function () {
    firstVisitPromo();
    firstVisitBook($this->maria, '10:00')->assertSessionHasNoErrors();

    firstVisitBook($this->maria, '12:00', ['expects_promotion' => true])->assertInertiaFlash('toast.type', 'error');

    expect(Booking::withoutGlobalScope('salon')->count())->toBe(1);
});

it('lets the owner start one from its card, as a discount and with its own words', function () {
    $form = [
        'card' => 'first_visit', 'mechanic' => 'discount', 'services' => [$this->cut->id], 'percent' => 20,
        'starts_on' => '2026-10-07', 'ends_on' => '2026-10-31',
    ];

    $this->actingAs($this->owner)->post(route('promotions.store'), $form)->assertSessionHasNoErrors();
    $promotion = Promotion::withoutGlobalScope('salon')->sole();

    expect($promotion->new_clients_only)->toBeTrue()
        ->and($promotion->name)->toBe('20 % menos en Corte en tu primera visita');

    $this->actingAs($this->owner)->post(route('promotions.store'), [...$form, 'mechanic' => 'gift', 'percent' => null, 'gift_service_id' => $this->cut->id])
        ->assertSessionHasErrors('mechanic');
});
