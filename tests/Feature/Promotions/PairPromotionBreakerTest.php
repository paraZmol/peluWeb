<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Salon\CreateSalon;
use App\Enums\BookingStatus;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PaymentMethod;
use App\Enums\PromotionStatus;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\SalonMember;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
| booking-breaker on "Ven con alguien" (2x1, step 6.7 d). Same Estudio Luz
| as PairPromotionTest: Corte S/ 35 (corto, 30 min) or S/ 45 (largo, +15
| min), Barba S/ 20; Ana does both, Wednesday to Friday, 9 to 18. "Now" is
| Wednesday 2026-10-07 08:00 in Lima.
*/

/**
 * @param  array<string, mixed>  $with
 */
function pbPromote(array $with = []): Promotion
{
    test()->actingAs(test()->owner)->post(route('promotions.store'), [
        'card' => 'pair', 'mechanic' => 'pair', 'services' => [test()->cut->id], 'percent' => 50,
        'starts_on' => '2026-10-07', 'ends_on' => '2026-10-31', ...$with,
    ])->assertSessionHasNoErrors();
    $promotion = Promotion::withoutGlobalScope('salon')->latest('id')->firstOrFail();
    $promotion->forceFill(['status' => PromotionStatus::Published])->save();

    return $promotion;
}

/**
 * Who books a short cut; who comes along, a long one.
 *
 * @return list<array<string, mixed>>
 */
function pbTwo(string $companion = 'Lucía'): array
{
    return [
        ['id' => test()->cut->id, 'variant' => test()->short->id],
        ['id' => test()->cut->id, 'variant' => test()->long->id, 'companion' => $companion],
    ];
}

/**
 * @param  list<array<string, mixed>>  $services
 */
function pbBook(array $services, string $time = '10:00'): TestResponse
{
    return test()->actingAs(test()->maria)->post(route('salons.booking.store', test()->salon), [
        'services' => $services,
        'start' => CarbonImmutable::parse("2026-10-07 {$time}", 'America/Lima')->getTimestamp(),
    ]);
}

function pbLatest(): Booking
{
    return Booking::withoutGlobalScope('salon')->with('services')->latest('id')->firstOrFail();
}

function pbDrop(Booking $booking, string $came = 'booker', ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->owner)->post(route('agenda.bookings.drop-companion', $booking), ['came' => $came]);
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0, 'min_lead_minutes' => 0])->save();
    $this->anaUser = User::factory()->create();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana', 'user_id' => $this->anaUser->id]);
    SalonMember::factory()->for($this->salon)->create(['user_id' => $this->anaUser->id]);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30, 'position' => 1]);
    $this->short = $this->cut->variants()->create(['label' => 'Corto', 'price_cents' => 3500, 'extra_minutes' => 0, 'position' => 0]);
    $this->long = $this->cut->variants()->create(['label' => 'Largo', 'price_cents' => 4500, 'extra_minutes' => 15, 'position' => 1]);
    $this->beard = Service::factory()->for($this->salon)->create(['name' => 'Barba', 'price_cents' => 2000, 'active_minutes' => 20, 'position' => 2]);
    $this->staff->services()->attach([$this->cut->id, $this->beard->id]);
    $this->maria = User::factory()->create();
});

describe('the name of who comes along', function () {
    it('refuses markup, line breaks, invisible characters and more than 60 characters', function (string $name) {
        pbPromote();

        pbBook(pbTwo($name))->assertSessionHasErrors('services.1.companion');

        expect(Booking::withoutGlobalScope('salon')->count())->toBe(0);
    })->with([
        'html' => '<b>Lucía</b>',
        'a line break' => "Lucía\nRamos",
        'a zero-width space' => "Lu\u{200B}cía",
        '61 characters' => str_repeat('á', 61),
    ]);

    it('keeps 60 characters, trims the edges, and treats blanks as no name at all', function () {
        pbPromote();

        pbBook(pbTwo(str_repeat('á', 60)))->assertSessionHasNoErrors();
        expect(pbLatest()->services[1]->companion_name)->toBe(str_repeat('á', 60));

        pbBook(pbTwo('  Lucía  '), '12:00')->assertSessionHasNoErrors();
        expect(pbLatest()->services[1]->companion_name)->toBe('Lucía');

        pbBook(pbTwo('   '), '14:00')->assertInertiaFlash('toast.type', 'error');
        expect(Booking::withoutGlobalScope('salon')->count())->toBe(2);
    });
});

describe('everywhere a selection is booked', function () {
    it('times both people one after the other in the picker, and stops before closing', function () {
        pbPromote();

        $slots = $this->actingAs($this->maria)->getJson(route('salons.booking.availability', [
            'salon' => $this->salon->slug, 'date' => '2026-10-07', 'services' => pbTwo(),
        ]))->assertOk()->json('slots');
        $times = array_column($slots, 'time');

        // 30 + 45 minutes: the last start that ends by 18:00 is 16:45.
        expect($times)->toContain('16:45')->not->toContain('17:00')
            ->and($slots[0]['promotion']['saves'] ?? null)->not->toBeNull();
    });

    it('lets the team book the pair at the desk, with the name and the promotion', function () {
        pbPromote();

        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), [
            'services' => pbTwo(), 'staff' => $this->staff->id, 'with_promotion' => true,
            'start' => CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima')->getTimestamp(),
            'client_name' => 'María Pérez',
        ])->assertSessionHasNoErrors();
        $booking = pbLatest();

        expect($booking->services->pluck('companion_name')->all())->toBe([null, 'Lucía'])
            ->and($booking->price_cents)->toBe(1750 + 4500)
            ->and($booking->ends_at->diffInMinutes($booking->starts_at, true))->toEqual(75);
    });

    it('books the pair through the app API too', function () {
        pbPromote();
        Sanctum::actingAs($this->maria);

        $this->postJson(route('api.v1.bookings.store', $this->salon), [
            'services' => pbTwo(),
            'start' => CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima')->getTimestamp(),
        ])->assertCreated();

        expect(pbLatest()->services->pluck('companion_name')->all())->toBe([null, 'Lucía'])
            ->and(pbLatest()->price_cents)->toBe(6250);
    });

    it('moves both people together, keeping their prices and their 75 minutes', function () {
        pbPromote();
        pbBook(pbTwo())->assertSessionHasNoErrors();
        $booking = pbLatest();

        $this->actingAs($this->owner)->post(route('agenda.bookings.move', $booking), [
            'start' => CarbonImmutable::parse('2026-10-08 11:00', 'America/Lima')->getTimestamp(),
        ])->assertSessionHasNoErrors();
        $moved = pbLatest();

        expect($moved->starts_at->setTimezone('America/Lima')->format('Y-m-d H:i'))->toBe('2026-10-08 11:00')
            ->and($moved->ends_at->diffInMinutes($moved->starts_at, true))->toEqual(75)
            ->and($moved->services->pluck('price_cents')->all())->toBe([1750, 4500])
            ->and($moved->services->pluck('companion_name')->all())->toBe([null, 'Lucía']);
    });

    it('"repetir cita" brings back who came along, so the repeat is not refused as a duplicate', function () {
        pbPromote();
        pbBook(pbTwo())->assertSessionHasNoErrors();

        $this->actingAs($this->maria)->get(route('salons.booking.create', ['salon' => $this->salon->slug, 'repetir' => pbLatest()->code]))
            ->assertInertia(fn ($page) => $page
                ->where('repeat.selections.0.companion', null)
                ->where('repeat.selections.1.companion', 'Lucía'));

        // Only one of them came: a plain visit, nobody to bring along.
        pbDrop(pbLatest(), 'companion')->assertSessionHasNoErrors();
        $this->actingAs($this->maria)->get(route('salons.booking.create', ['salon' => $this->salon->slug, 'repetir' => pbLatest()->code]))
            ->assertInertia(fn ($page) => $page->where('repeat.selections.0.companion', null));
    });
});

describe('against the other promotions', function () {
    it('gives the pair when it saves more, and a bigger discount when that saves more', function (int $percent, int $price) {
        pbPromote();
        $this->actingAs($this->owner)->post(route('promotions.store'), [
            'card' => 'discount', 'mechanic' => 'discount', 'services' => [$this->cut->id], 'percent' => $percent,
            'starts_on' => '2026-10-07', 'ends_on' => '2026-10-31',
        ])->assertSessionHasNoErrors();
        Promotion::withoutGlobalScope('salon')->latest('id')->firstOrFail()->forceFill(['status' => PromotionStatus::Published])->save();

        pbBook(pbTwo())->assertSessionHasNoErrors();

        expect(pbLatest()->price_cents)->toBe($price);
    })->with([
        // Pair: 1750 off. 20 % of 8000: 1600 off.
        'the pair wins' => [20, 6250],
        // 30 % of 8000: 2400 off.
        'the discount wins' => [30, 5600],
    ]);

    it('does not give the pair on a service it does not include', function () {
        pbPromote();
        $this->beard->forceFill(['price_cents' => 2000])->save();

        pbBook([['id' => $this->beard->id], ['id' => $this->beard->id, 'companion' => 'Lucía']])->assertSessionHasNoErrors();

        expect(pbLatest()->price_cents)->toBe(4000)->and(pbLatest()->promotion_id)->toBeNull();
    });

    it('is only for services with one fixed price', function (string $field) {
        $this->cut->forceFill([$field => true])->save();

        $this->actingAs($this->owner)->post(route('promotions.store'), [
            'card' => 'pair', 'mechanic' => 'pair', 'services' => [$this->cut->id], 'percent' => 50,
            'starts_on' => '2026-10-07', 'ends_on' => '2026-10-31',
        ])->assertSessionHasErrors('services');
    })->with(['requires_evaluation', 'is_addon']);
});

describe('when only one of them comes', function () {
    it('never leaves a paid deposit above what is left to charge', function () {
        // The deposit is on the total with the promotion: 6250. Who booked
        // the short cut comes alone, whose normal price is 3500.
        $this->salon->forceFill(['deposit_percent' => 100])->save();
        pbPromote();
        pbBook(pbTwo())->assertSessionHasNoErrors();
        $booking = pbLatest();
        app(ChangeBookingStatus::class)->confirm($booking, $this->owner, PaymentMethod::Yape);

        pbDrop($booking)->assertInertiaFlash('toast.type', 'error');

        // Untouched, and it can still be settled as booked.
        $booking = pbLatest();
        expect($booking->services)->toHaveCount(2)->and($booking->price_cents)->toBe(6250);
        app(ChangeBookingStatus::class)->start($booking, $this->owner);
        app(ChangeBookingStatus::class)->complete(pbLatest(), $this->owner);
        expect(pbLatest()->status)->toBe(BookingStatus::Completed);
    });

    it('scales a deposit not yet paid to what is left, so confirming it later cannot overpay', function () {
        $this->salon->forceFill(['deposit_percent' => 100])->save();
        pbPromote();
        pbBook(pbTwo())->assertSessionHasNoErrors();
        $booking = pbLatest();
        expect($booking->status)->toBe(BookingStatus::Pending)->and($booking->deposit_cents)->toBe(6250);

        pbDrop($booking)->assertSessionHasNoErrors();
        $booking = pbLatest();

        expect($booking->price_cents)->toBe(3500)->and($booking->deposit_cents)->toBe(3500);

        app(ChangeBookingStatus::class)->confirm($booking, $this->owner, PaymentMethod::Yape);
        app(ChangeBookingStatus::class)->start(pbLatest(), $this->owner);
        app(ChangeBookingStatus::class)->complete(pbLatest(), $this->owner);
        expect(pbLatest())->status->toBe(BookingStatus::Completed)->charged_cents->toBe(3500);
    });

    it('charges the balance when the paid deposit fits in what is left', function () {
        $this->salon->forceFill(['deposit_percent' => 20])->save();
        pbPromote();
        pbBook(pbTwo())->assertSessionHasNoErrors();
        app(ChangeBookingStatus::class)->confirm(pbLatest(), $this->owner, PaymentMethod::Yape);

        pbDrop(pbLatest())->assertSessionHasNoErrors();
        app(ChangeBookingStatus::class)->start(pbLatest(), $this->owner);
        app(ChangeBookingStatus::class)->complete(pbLatest(), $this->owner);

        // 20 % of 6250 = 1250 paid ahead; 3500 − 1250 = 2250 at the end.
        expect(pbLatest())->charged_cents->toBe(3500)
            ->and(pbLatest()->payments->pluck('amount_cents')->sum())->toBe(3500);
    });

    it('does nothing to a visit that is over or called off', function (string $status) {
        pbPromote();
        pbBook(pbTwo())->assertSessionHasNoErrors();
        pbLatest()->forceFill(['status' => BookingStatus::from($status)])->save();

        pbDrop(pbLatest())->assertInertiaFlash('toast.type', 'error');

        expect(pbLatest()->services)->toHaveCount(2)->and(pbLatest()->price_cents)->toBe(6250);
    })->with(['completed', 'cancelled', 'no_show', 'expired']);

    // Security audit, 2026-10-14: it lowers what is charged, like a discount,
    // so it takes the cash permission — not even the professional of the visit.
    it('takes the cash permission: not the professional of the visit, nor a colleague', function () {
        pbPromote();
        pbBook(pbTwo())->assertSessionHasNoErrors();
        $colleague = User::factory()->create();
        Staff::factory()->for($this->salon)->working([3], '09:00', '18:00')->create(['name' => 'Beto', 'user_id' => $colleague->id]);
        SalonMember::factory()->for($this->salon)->create(['user_id' => $colleague->id]);

        pbDrop(pbLatest(), 'booker', $colleague)->assertForbidden();
        pbDrop(pbLatest(), 'booker', $this->anaUser)->assertForbidden();
        expect(pbLatest()->services)->toHaveCount(2);

        pbDrop(pbLatest(), 'booker', $this->owner)->assertSessionHasNoErrors();
        expect(pbLatest()->services)->toHaveCount(1);
    });

    it('shows who comes along on the agenda only to who may take them off', function () {
        pbPromote();
        pbBook(pbTwo())->assertSessionHasNoErrors();
        $companionFor = fn (User $viewer) => $this->actingAs($viewer)->get(route('agenda.index', ['date' => '2026-10-07']))
            ->assertOk()->inertiaProps('bookings.0.companion');

        expect($companionFor($this->owner))->toBe('Lucía')
            ->and($companionFor($this->anaUser))->toBeNull();
    });

    it('commissions on what is charged for each person', function () {
        $this->staff->forceFill(['commission_percent' => 40])->save();
        pbPromote();
        pbBook(pbTwo())->assertSessionHasNoErrors();

        app(ChangeBookingStatus::class)->start(pbLatest(), $this->owner);
        app(ChangeBookingStatus::class)->complete(pbLatest(), $this->owner);

        expect(pbLatest()->commission_cents)->toBe(2500);
    });
});
