<?php

use App\Actions\Accounting\SalonReport;
use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\RescheduleBooking;
use App\Actions\Promotions\SalonPromotions;
use App\Actions\Salon\CreateSalon;
use App\Data\Accounting\Period;
use App\Data\Booking\BookingMove;
use App\Data\Booking\NewBooking;
use App\Domain\Accounting\Checkout;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PaymentMethod;
use App\Enums\PromotionMechanic;
use App\Enums\PromotionStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Promotion;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| booking-breaker against step 6.7 (d), "Combo a precio cerrado"
| (docs/promociones.md). The combo is written through the owner's form, so
| its saving is what the server worked out from the price typed.
|
| Estudio Luz: Corte S/ 35, Barba S/ 20, Rasurado S/ 12. Ana does all of
| them, Wednesday to Friday, 9 to 18, and earns 40 %. No deposit unless a
| test asks for one. "Now" is Wednesday 2026-10-07 08:00 in Lima.
*/

/**
 * @param  array<string, mixed>  $with
 */
function cbrkSave(array $with = [], ?Promotion $promotion = null): TestResponse
{
    $form = [
        'card' => 'combo',
        'mechanic' => 'combo',
        'services' => [test()->cut->id, test()->beard->id],
        'combo_price' => '45',
        'starts_on' => '2026-10-07',
        'ends_on' => '2026-10-16',
        ...$with,
    ];

    return $promotion === null
        ? test()->actingAs(test()->owner)->post(route('promotions.store'), $form)
        : test()->actingAs(test()->owner)->put(route('promotions.update', $promotion), $form);
}

/**
 * @param  array<string, mixed>  $with
 */
function cbrkCombo(array $with = []): Promotion
{
    cbrkSave($with)->assertSessionHasNoErrors();
    $promotion = Promotion::withoutGlobalScope('salon')->latest('id')->firstOrFail();
    test()->actingAs(test()->owner)->post(route('promotions.publish', $promotion))->assertSessionHasNoErrors();

    return $promotion->refresh();
}

/**
 * @param  list<Service|array{service_id: int, variant_id: int|null}>  $services
 */
function cbrkBook(array $services, string $at = '2026-10-07 10:00', bool $withPromotion = true, BookingSource $source = BookingSource::Client): Booking
{
    $salon = test()->salon->refresh();

    return app(CreateBooking::class)->handle(new NewBooking(
        salon: $salon,
        client: test()->client,
        selections: array_map(fn (Service|array $service): array => is_array($service) ? $service : ['service_id' => $service->id, 'variant_id' => null], $services),
        staff: test()->staff,
        startsAt: CarbonImmutable::parse($at, $salon->timezone),
        source: $source,
        withPromotion: $withPromotion,
    ));
}

/**
 * @return list<int>
 */
function cbrkLines(Booking $booking): array
{
    return array_map(intval(...), BookingService::query()->where('booking_id', $booking->id)->orderBy('position')->pluck('price_cents')->all());
}

/**
 * Lines add up to the booking, list − charged is the promotion, and no
 * line ends at S/ 0 or above its normal price.
 *
 * @return list<string>
 */
function cbrkMoneyProblems(Booking $booking): array
{
    $lines = BookingService::query()->where('booking_id', $booking->id)->get();
    $problems = [];

    if ((int) $lines->sum('price_cents') !== $booking->price_cents) {
        $problems[] = "lines add up to {$lines->sum('price_cents')}, the booking says {$booking->price_cents}";
    }

    if ((int) $lines->sum(fn (BookingService $line) => $line->list_price_cents - $line->price_cents) !== $booking->promotion_cents) {
        $problems[] = "list − charged is not promotion_cents {$booking->promotion_cents}";
    }

    foreach ($lines as $line) {
        if ($line->price_cents <= 0 || $line->price_cents > $line->list_price_cents) {
            $problems[] = "line {$line->name}: charged {$line->price_cents}, list {$line->list_price_cents}";
        }
    }

    return $problems;
}

function cbrkComplete(Booking $booking, ?Checkout $checkout = null): Booking
{
    $status = app(ChangeBookingStatus::class);

    if ($booking->status === BookingStatus::Pending) {
        $booking = $status->confirm($booking, test()->owner, PaymentMethod::Yape);
    }

    test()->travelTo($booking->starts_at);
    $status->start($booking, test()->owner);

    return $status->complete($booking, test()->owner, $checkout);
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 0, 'min_lead_minutes' => 0, 'cancel_window_hours' => 0])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana']);
    $this->staff->forceFill(['commission_percent' => 40])->save();
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30, 'position' => 1]);
    $this->beard = Service::factory()->for($this->salon)->create(['name' => 'Barba', 'price_cents' => 2000, 'active_minutes' => 20, 'position' => 2]);
    $this->shave = Service::factory()->for($this->salon)->create(['name' => 'Rasurado', 'price_cents' => 1200, 'active_minutes' => 15, 'position' => 3]);
    $this->staff->services()->attach([$this->cut->id, $this->beard->id, $this->shave->id]);

    $this->maria = User::factory()->create();
    $this->client = SalonClient::factory()->for($this->salon)->create(['user_id' => $this->maria->id]);
});

describe('money', function () {
    it('keeps the same saving with a professional\'s own lower price, and never charges more than separately', function (int $ownBeard, int $expected) {
        cbrkCombo();
        DB::table('service_staff')->where('staff_id', $this->staff->id)->where('service_id', $this->beard->id)->update(['price_cents' => $ownBeard]);

        $booking = cbrkBook([$this->cut, $this->beard]);

        expect($booking->price_cents)->toBe($expected)
            ->and($booking->price_cents)->toBeLessThanOrEqual(3500 + $ownBeard)
            ->and(cbrkMoneyProblems($booking))->toBe([]);
    })->with([
        'S/ 8 instead of S/ 20' => [800, 3500 + 800 - 1000],
        // 1000 × 3500 / 3505 = 998 off the cut; the 5 céntimos of the beard take the other 2.
        'five céntimos' => [5, 3500 + 5 - 1000],
    ]);

    it('does not apply when the saving does not fit in what the professional charges', function () {
        cbrkCombo();
        DB::table('service_staff')->where('staff_id', $this->staff->id)->whereIn('service_id', [$this->cut->id, $this->beard->id])->update(['price_cents' => 500]);

        $booking = cbrkBook([$this->cut, $this->beard]);

        // S/ 10 over S/ 10: it would make the visit free.
        expect($booking->price_cents)->toBe(1000)->and($booking->promotion_id)->toBeNull()->and($booking->promotion_cents)->toBe(0);
    });

    it('charges exactly normal − saving with three services of odd céntimos, in any order', function (array $order) {
        $this->cut->forceFill(['price_cents' => 3333])->save();
        $this->beard->forceFill(['price_cents' => 1777])->save();
        $this->shave->forceFill(['price_cents' => 999])->save();
        // 61.09 by separate, 50.08 together: 11.01 off (18 %).
        $combo = cbrkCombo(['services' => [$this->cut->id, $this->beard->id, $this->shave->id], 'combo_price' => '50.08']);

        $booking = cbrkBook(array_map(fn (string $name) => $this->{$name}, $order));

        expect($combo->amount_cents)->toBe(1101)
            ->and($booking->price_cents)->toBe(6109 - 1101)
            ->and($booking->promotion_cents)->toBe(1101)
            ->and(cbrkMoneyProblems($booking))->toBe([]);
    })->with([
        'catalogue order' => [['cut', 'beard', 'shave']],
        'cheapest first' => [['shave', 'beard', 'cut']],
    ]);

    it('charges an extra service at its normal price beside the combo', function () {
        cbrkCombo();

        $booking = cbrkBook([$this->shave, $this->cut, $this->beard]);

        expect(cbrkLines($booking))->toBe([1200, 3500 - 636, 2000 - 364])->and(cbrkMoneyProblems($booking))->toBe([]);
    });

    it('asks the deposit on the price with the combo and settles the rest exactly', function () {
        $this->salon->forceFill(['deposit_percent' => 20])->save();
        cbrkCombo();

        $booking = cbrkBook([$this->cut, $this->beard]);
        $done = cbrkComplete($booking, new Checkout(0, ['cash' => 4500 - 900]));

        expect([$booking->price_cents, $booking->deposit_cents, $booking->status])->toBe([4500, 900, BookingStatus::Pending])
            ->and([$done->charged_cents, (int) $done->payments()->withoutGlobalScopes()->sum('amount_cents')])->toBe([4500, 4500]);
    });
});

describe('against other promotions', function () {
    it('lets the one that saves the most win', function (Closure $other, ?string $winner) {
        $combo = cbrkCombo();
        $rival = $other();

        $booking = cbrkBook([$this->cut, $this->beard]);

        expect($booking->promotion_id)->toBe($winner === 'combo' ? $combo->id : $rival->id);
    })->with([
        '20 % off the cut saves S/ 7' => [fn () => cbrkCombo(['card' => 'discount', 'mechanic' => 'discount', 'combo_price' => null, 'percent' => 20, 'services' => [test()->cut->id]]), 'combo'],
        '40 % off the cut saves S/ 14' => [fn () => cbrkCombo(['card' => 'discount', 'mechanic' => 'discount', 'combo_price' => null, 'percent' => 40, 'services' => [test()->cut->id]]), 'rival'],
        'the beard as a gift saves S/ 20' => [fn () => cbrkCombo(['card' => 'gift', 'mechanic' => 'gift', 'combo_price' => null, 'gift_service_id' => test()->beard->id, 'services' => [test()->cut->id]]), 'rival'],
    ]);
});

describe('the till and commissions', function () {
    it('pays commission on what each service of the combo charged', function () {
        DB::table('service_staff')->where('staff_id', $this->staff->id)->where('service_id', $this->beard->id)->update(['commission_cents' => 1800]);
        cbrkCombo();

        $done = cbrkComplete(cbrkBook([$this->cut, $this->beard]));

        // 40 % of the 28.64 cut = 11.46; the fixed S/ 18 of the beard is capped at its 16.36.
        expect([$done->charged_cents, $done->commission_cents])->toBe([4500, 1146 + 1636]);
    });

    it('has no way to drop a service of the combo at the till: the lines stay as booked', function () {
        cbrkCombo();
        $booking = cbrkBook([$this->cut, $this->beard]);
        $status = app(ChangeBookingStatus::class);
        $this->travelTo($booking->starts_at);
        $status->start($booking, $this->owner);

        $this->actingAs($this->owner)->post(route('agenda.bookings.complete', $booking), [
            'services' => [['id' => $this->cut->id]],
            'payments' => [['method' => 'cash', 'amount' => '45.00']],
        ])->assertSessionHasNoErrors();

        expect($booking->refresh()->charged_cents)->toBe(4500)->and(cbrkLines($booking))->toBe([3500 - 636, 2000 - 364]);
    });

    it('reports what the combo took off apart from a manual discount', function () {
        cbrkCombo();
        cbrkComplete(cbrkBook([$this->cut, $this->beard]), new Checkout(500, ['cash' => 4000]));

        $report = app(SalonReport::class)->handle($this->salon, Period::inSalon($this->salon->refresh(), '2026-10-07', '2026-10-07'));

        expect([$report['totals']['servicesCents'], $report['totals']['promotionsCents'], $report['totals']['discountsCents']])->toBe([4000, 1000, 500]);
    });
});

describe('moving it', function () {
    it('lets the client move it only inside the combo\'s dates; the salon anywhere, at the same price', function () {
        cbrkCombo(['ends_on' => '2026-10-09']);
        $booking = cbrkBook([$this->cut, $this->beard]);
        $move = app(RescheduleBooking::class);

        expect(fn () => $move->handle(new BookingMove($booking, CarbonImmutable::parse('2026-10-14 10:00', 'America/Lima'), byClient: true, actor: $this->maria)))
            ->toThrow(BookingException::class);

        $moved = $move->handle(new BookingMove($booking->refresh(), CarbonImmutable::parse('2026-10-14 10:00', 'America/Lima'), byClient: false, actor: $this->owner));

        expect([$moved->price_cents, $moved->promotion_cents])->toBe([4500, 1000])->and(cbrkLines($moved))->toBe([3500 - 636, 2000 - 364]);
    });
});

describe('the team\'s booking', function () {
    it('marks the combo by default and books the normal price when unticked', function (?bool $withPromotion, int $expected) {
        cbrkCombo();

        $this->actingAs($this->owner)->post(route('agenda.bookings.store'), [
            'services' => [['id' => $this->cut->id], ['id' => $this->beard->id]],
            'start' => CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima')->getTimestamp(),
            'client_id' => $this->client->id,
            ...($withPromotion === null ? [] : ['with_promotion' => $withPromotion]),
        ])->assertSessionHasNoErrors();

        expect(Booking::withoutGlobalScope('salon')->sole()->price_cents)->toBe($expected);
    })->with([
        'left as it comes' => [null, 4500],
        'ticked' => [true, 4500],
        'unticked' => [false, 5500],
    ]);
});

describe('after publishing', function () {
    it('refuses to change its services or its price once booked, and still takes a later end', function () {
        $combo = cbrkCombo();
        cbrkBook([$this->cut, $this->beard]);

        cbrkSave(['services' => [$this->cut->id, $this->shave->id], 'combo_price' => '40'], $combo);
        cbrkSave(['combo_price' => '44'], $combo);

        expect($combo->refresh()->amount_cents)->toBe(1000)
            ->and($combo->services()->pluck('services.id')->sort()->values()->all())->toBe([$this->cut->id, $this->beard->id]);

        cbrkSave(['ends_on' => '2026-10-23'], $combo)->assertSessionHasNoErrors();

        expect($combo->refresh()->amount_cents)->toBe(1000)->and($combo->ends_on->toDateString())->toBe('2026-10-23');
    });

    // MEDIO, fixed in PromotionRequest::keepsBookedSaving: the 5-50 % range was
    // checked again against today's prices, so a combo whose service got
    // dearer could not even be given a later end.
    it('fixed: still takes a later end with the form as shown, after a service of the combo got dearer', function () {
        $combo = cbrkCombo();
        cbrkBook([$this->cut, $this->beard]);
        // S/ 10 off S/ 260 is under 5 %.
        $this->cut->forceFill(['price_cents' => 24000])->save();
        $form = collect(app(SalonPromotions::class)->handle($this->salon->refresh()))->firstWhere('id', $combo->id)['form'];

        cbrkSave(['ends_on' => '2026-10-23', 'combo_price' => $form['combo_price']], $combo)->assertSessionHasNoErrors();

        expect($combo->refresh()->ends_on->toDateString())->toBe('2026-10-23')->and($combo->amount_cents)->toBe(1000);
    });

    // Found by this breaker: the stored title used to freeze the price
    // ("por S/ 45") while a later catalogue price charged S/ 50.
    it('never advertises a combo price that is no longer what it costs', function () {
        cbrkCombo();
        // Corte goes from S/ 35 to S/ 40: Corte + Barba with the combo is now S/ 50.
        $this->cut->forceFill(['price_cents' => 4000])->save();

        $booking = cbrkBook([$this->cut, $this->beard]);

        // The stored name has no price in it; the price is written live.
        $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page
            ->where('promotions.items.0.combo.price', '50.00')
            ->where('promotions.items.0.name', 'Combo Corte + Barba')
            // The price box says it; no second line repeats it.
            ->where('promotions.items.0.summary', null));
        expect($booking->price_cents)->toBe(5000)->and($booking->promotion_name)->toBe('Combo Corte + Barba');
    });

    it('copies a combo as a draft with the same services and saving', function () {
        $combo = cbrkCombo();

        $this->actingAs($this->owner)->post(route('promotions.duplicate', $combo))->assertSessionHasNoErrors();
        $copy = Promotion::withoutGlobalScope('salon')->latest('id')->firstOrFail();

        expect([$copy->mechanic, $copy->card, $copy->amount_cents, $copy->status])->toBe([PromotionMechanic::Combo, 'combo', 1000, PromotionStatus::Draft])
            ->and($copy->services()->pluck('services.id')->sort()->values()->all())->toBe([$this->cut->id, $this->beard->id]);
    });

    it('stops offering it when one of its services is switched off or deleted', function (Closure $remove) {
        $combo = cbrkCombo(['services' => [$this->cut->id, $this->beard->id, $this->shave->id], 'combo_price' => '55']);
        $remove($this->shave);

        $booking = cbrkBook([$this->cut, $this->beard]);

        expect($booking->promotion_id)->toBeNull()->and($booking->price_cents)->toBe(5500);
        $this->get(route('salons.show', $this->salon))->assertInertia(fn (Assert $page) => $page->where('promotions.items', []));
        expect(collect(app(SalonPromotions::class)->handle($this->salon->refresh()))->firstWhere('id', $combo->id)['state'])->toBe('needs_review');
    })->with([
        'switched off' => [fn (Service $service) => $service->forceFill(['is_active' => false])->save()],
        'deleted' => [fn (Service $service) => $service->delete()],
    ]);
});
