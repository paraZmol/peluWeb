<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\RescheduleBooking;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\BookingMove;
use App\Data\Booking\NewBooking;
use App\Data\Booking\QuoteItem;
use App\Domain\Accounting\Checkout;
use App\Domain\Promotions\PromotionPicker;
use App\Domain\Promotions\PromotionTerms;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\PromotionMechanic;
use App\Enums\PromotionStatus;
use App\Enums\ScheduleAccess;
use App\Exceptions\BookingException;
use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Promotion;
use App\Models\SalonClient;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/*
| booking-breaker against step 6.7 (a), the promotions engine
| (docs/promociones.md). Tests named "BUG:" fail on purpose: each one is a
| finding, with the rule it breaks in its comment. The rest are attacks the
| engine withstood.
|
| Estudio Luz asks a 20 % deposit; Ana works Wednesday to Friday, 9 to 18,
| and earns 40 %. Corte costs S/ 35, Rasurado S/ 12. "Now" is Wednesday
| 2026-10-07 08:00 in Lima; the server runs in UTC.
*/

/**
 * @param  array<string, mixed>  $with
 * @param  list<Service>|null  $services
 */
function pbrkPromo(array $with = [], ?array $services = null): Promotion
{
    $promotion = new Promotion;
    $promotion->forceFill([
        'salon_id' => test()->salon->id,
        'mechanic' => PromotionMechanic::Discount,
        'card' => 'discount',
        'name' => '20 % menos en Corte',
        'status' => PromotionStatus::Published,
        'starts_on' => '2026-10-05',
        'ends_on' => '2026-10-18',
        'percent' => 20,
        'share_code' => Str::random(10),
        ...$with,
    ])->save();
    $promotion->services()->sync(array_map(fn (Service $service) => $service->id, $services ?? [test()->cut]));

    return $promotion;
}

/**
 * @param  array<string, mixed>  $with
 */
function pbrkGift(array $with = [], ?array $services = null): Promotion
{
    return pbrkPromo(['mechanic' => PromotionMechanic::Gift, 'card' => 'gift', 'name' => 'Rasurado gratis con tu Corte', 'percent' => null, 'gift_service_id' => test()->shave->id, ...$with], $services);
}

/**
 * @param  list<Service|array{service_id: int, variant_id: int|null}>|null  $services
 */
function pbrkBook(string $at, ?SalonClient $client = null, ?array $services = null, BookingSource $source = BookingSource::Client, bool $withPromotion = true, ?Staff $staff = null): Booking
{
    $salon = test()->salon->refresh();

    return app(CreateBooking::class)->handle(new NewBooking(
        salon: $salon,
        client: $client ?? test()->client,
        selections: array_map(fn (Service|array $service): array => is_array($service) ? $service : ['service_id' => $service->id, 'variant_id' => null], $services ?? [test()->cut]),
        staff: $staff ?? test()->staff,
        startsAt: CarbonImmutable::parse($at, $salon->timezone),
        source: $source,
        withPromotion: $withPromotion,
    ));
}

/**
 * The invariants of the money of a booking, whatever promotion it took:
 * the lines add up to the booking, the saving is list − charged, nothing
 * is negative and nothing costs more than its normal price.
 *
 * @return list<string>
 */
function pbrkMoneyProblems(Booking $booking): array
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
        if ($line->price_cents < 0 || $line->list_price_cents === null || $line->price_cents > $line->list_price_cents) {
            $problems[] = "line {$line->name}: charged {$line->price_cents}, list ".var_export($line->list_price_cents, true);
        }
    }

    if (($booking->promotion_id === null) !== ($booking->promotion_cents === 0)) {
        $problems[] = "promotion_id and promotion_cents {$booking->promotion_cents} disagree";
    }

    return $problems;
}

function pbrkAt(string $time, string $timezone = 'America/Lima'): CarbonImmutable
{
    return CarbonImmutable::parse($time, $timezone);
}

/**
 * Deposit paid, the client in the chair.
 */
function pbrkInService(Booking $booking): Booking
{
    if ($booking->status === BookingStatus::Pending) {
        $booking = app(ChangeBookingStatus::class)->confirm($booking, test()->owner);
    }

    test()->travelTo($booking->starts_at);

    return app(ChangeBookingStatus::class)->start($booking, test()->owner);
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(pbrkAt('2026-10-07 08:00'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 20])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana']);
    $this->staff->forceFill(['commission_percent' => 40])->save();
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->shave = Service::factory()->for($this->salon)->create(['name' => 'Rasurado', 'price_cents' => 1200, 'active_minutes' => 15]);
    $this->staff->services()->attach([$this->cut->id, $this->shave->id]);

    $this->maria = User::factory()->create();
    $this->client = SalonClient::factory()->for($this->salon)->create(['user_id' => $this->maria->id]);
});

describe('3. money', function () {
    it('keeps the books of the booking whole with odd prices and percentages', function (int $priceCents, int $percent, int $expected) {
        $this->cut->forceFill(['price_cents' => $priceCents])->save();
        pbrkPromo(['percent' => $percent]);

        $booking = pbrkBook('2026-10-07 10:00');

        expect($booking->price_cents)->toBe($expected)
            ->and($booking->price_cents % 10)->toBe(0)
            ->and($booking->deposit_cents)->toBe(intdiv($expected * 20 + 50, 100))
            ->and(pbrkMoneyProblems($booking))->toBe([]);
    })->with([
        'S/ 33.33 at 70 %' => [3333, 70, 990],
        'S/ 33.33 at 33 %' => [3333, 33, 2230],
        'S/ 33.33 at 5 %' => [3333, 5, 3160],
        'S/ 10.05 at 5 %' => [1005, 5, 950],
        'S/ 0.99 at 50 %' => [99, 50, 40],
        'S/ 9999.99 at 7 %' => [999999, 7, 929990],
    ]);

    // BAJO. "Redondeo: el precio con promoción de cada servicio, hacia abajo
    // a 10 céntimos" and a discount is 5 to 70 %: here 20 % gives the service
    // away. PromotionTerms::percentOff rounds down without a floor.
    it('fixed: a 20 % discount on a service of a few céntimos charges nothing at all', function () {
        $this->cut->forceFill(['price_cents' => 5])->save();
        pbrkPromo();

        $booking = pbrkBook('2026-10-07 10:00');

        expect($booking->price_cents)->toBeGreaterThan(0, 'a percentage of 20 % ended up as 100 %');
    });

    // BAJO. The rounding rule is for every promotion; PromotionTerms::amountOff
    // skips it.
    it('fixed: a fixed amount off leaves a price that is not rounded to 10 céntimos', function () {
        $this->cut->forceFill(['price_cents' => 3333])->save();
        pbrkPromo(['percent' => null, 'amount_cents' => 1000, 'name' => 'S/ 10 menos']);

        $booking = pbrkBook('2026-10-07 10:00');

        expect(pbrkMoneyProblems($booking))->toBe([])
            ->and($booking->price_cents % 10)->toBe(0, "charged {$booking->price_cents}");
    });

    it('takes a fixed amount once per booking, from one included service only', function () {
        pbrkPromo(['percent' => null, 'amount_cents' => 1000, 'name' => 'S/ 10 menos'], [$this->cut, $this->shave]);

        $booking = pbrkBook('2026-10-07 10:00', services: [$this->shave, $this->cut]);

        // From the first included one, in the order they are done.
        expect($booking->price_cents)->toBe(3700)->and($booking->promotion_cents)->toBe(1000)
            ->and(pbrkMoneyProblems($booking))->toBe([]);
    });

    // MEDIO. "El monto fijo […] menor que el incluido más barato": the owner's
    // form can only check the catalogue price, and the professional's own
    // price is lower. The engine clamps at zero, so the visit is free, it is
    // born confirmed with no deposit, and an online client holds the slot
    // for nothing. PromotionTerms::amountOff / Promotion::isIntact should
    // refuse a fixed amount that is not below the price actually quoted.
    it('fixed: a fixed amount above the professional\'s own price gives the visit away, confirmed and without a deposit', function () {
        DB::table('service_staff')->where('staff_id', $this->staff->id)->where('service_id', $this->cut->id)->update(['price_cents' => 800]);
        pbrkPromo(['percent' => null, 'amount_cents' => 1000, 'name' => 'S/ 10 menos']);

        $booking = pbrkBook('2026-10-07 10:00');

        expect(pbrkMoneyProblems($booking))->toBe([])
            ->and([$booking->price_cents, $booking->status, $booking->deposit_cents])->not->toBe([0, BookingStatus::Confirmed, 0]);
    });

    it('leaves a service alone when the fixed amount is not below what it costs with that professional', function () {
        DB::table('service_staff')->where('staff_id', $this->staff->id)->where('service_id', $this->cut->id)->update(['price_cents' => 800, 'commission_cents' => 500]);
        pbrkPromo(['percent' => null, 'amount_cents' => 1000, 'name' => 'S/ 10 menos']);

        $booking = pbrkBook('2026-10-07 10:00');

        // Nothing is given away: it is booked at its normal price, with its deposit.
        expect([$booking->price_cents, $booking->promotion_id, $booking->promotion_cents])->toBe([800, null, 0])
            ->and(pbrkMoneyProblems($booking))->toBe([]);
    });

    it('prices the promotion from the professional\'s own price and from the chosen variant', function () {
        $colour = Service::factory()->for($this->salon)->create(['name' => 'Color', 'price_cents' => 9000, 'active_minutes' => 30]);
        $long = new ServiceVariant;
        $long->forceFill(['service_id' => $colour->id, 'label' => 'Largo', 'price_cents' => 15050, 'extra_minutes' => 15, 'position' => 0])->save();
        $this->staff->services()->attach($colour->id, ['price_cents' => 7000]);
        DB::table('service_staff')->where('staff_id', $this->staff->id)->where('service_id', $this->cut->id)->update(['price_cents' => 3010]);
        pbrkPromo(['percent' => 15], [$this->cut, $colour]);

        $booking = pbrkBook('2026-10-07 10:00', services: [$this->cut, ['service_id' => $colour->id, 'variant_id' => $long->id]]);
        $lines = BookingService::query()->where('booking_id', $booking->id)->orderBy('position')->get();

        // 30.10 → 25.585 → 25.50; 150.50 → 127.925 → 127.90.
        expect([$lines[0]->list_price_cents, $lines[0]->price_cents])->toBe([3010, 2550])
            ->and([$lines[1]->list_price_cents, $lines[1]->price_cents])->toBe([15050, 12790])
            ->and($booking->price_cents)->toBe(15340)->and($booking->promotion_cents)->toBe(2720)
            ->and($booking->deposit_cents)->toBe(3068)
            ->and(pbrkMoneyProblems($booking))->toBe([]);
    });

    it('gives the gift only with another service that earns it, even when the gift is among the included ones', function () {
        pbrkGift(services: [$this->cut, $this->shave]);

        $alone = pbrkBook('2026-10-07 10:00', services: [$this->shave]);
        $giftFirst = pbrkBook('2026-10-07 11:00', services: [$this->shave, $this->cut]);

        expect($alone->promotion_id)->toBeNull()->and($alone->price_cents)->toBe(1200)
            ->and($giftFirst->price_cents)->toBe(3500)->and($giftFirst->promotion_cents)->toBe(1200)
            ->and(pbrkMoneyProblems($alone))->toBe([])->and(pbrkMoneyProblems($giftFirst))->toBe([]);
    });

    // MEDIO. "…regalamos: un solo servicio con precio fijo: sin variantes".
    // Nothing in the engine holds that: the client picks the dearest option
    // of the gift and it is free. PromotionTerms::gift (or
    // Promotion::isIntact) must not give away a service that has variants.
    it('fixed: a gift service with options is given away at its most expensive option', function () {
        $treatment = Service::factory()->for($this->salon)->create(['name' => 'Tratamiento', 'price_cents' => 2000, 'active_minutes' => 15]);

        foreach ([['Básico', 2000], ['Premium', 20000]] as $position => [$label, $cents]) {
            $variant = new ServiceVariant;
            $variant->forceFill(['service_id' => $treatment->id, 'label' => $label, 'price_cents' => $cents, 'extra_minutes' => 0, 'position' => $position])->save();
        }

        $this->staff->services()->attach($treatment->id);
        pbrkGift(['gift_service_id' => $treatment->id, 'name' => 'Tratamiento gratis con tu Corte']);

        $booking = pbrkBook('2026-10-07 10:00', services: [$this->cut, ['service_id' => $treatment->id, 'variant_id' => $variant->id]]);

        expect($booking->promotion_cents)->toBeLessThan(20000, "S/ 200 given away with a S/ 35 haircut (the booking costs {$booking->price_cents})");
    });

    it('asks a VIP for their own deposit percentage of the price already with the promotion', function () {
        $this->salon->forceFill(['vip_deposit_percent' => 50])->save();
        $vip = SalonClient::factory()->for($this->salon)->create(['is_vip' => true]);
        pbrkPromo(['percent' => 15]);

        $booking = pbrkBook('2026-10-07 10:00', $vip);

        // 35.00 → 29.75 → 29.70; half of it.
        expect($booking->price_cents)->toBe(2970)->and($booking->deposit_cents)->toBe(1485)->and($booking->is_vip)->toBeTrue();
    });

    it('pays no commission for the gift line, whatever rule the professional has for it', function () {
        DB::table('service_staff')->where('staff_id', $this->staff->id)->where('service_id', $this->shave->id)->update(['commission_cents' => 300]);
        pbrkGift();

        $done = app(ChangeBookingStatus::class)->complete(pbrkInService(pbrkBook('2026-10-07 10:00', services: [$this->cut, $this->shave])), $this->owner);

        // 40 % of the S/ 35 haircut; the fixed S/ 3 of the shave is capped at what it charged: nothing.
        expect([$done->charged_cents, $done->commission_cents, $done->promotion_cents])->toBe([3500, 1400, 1200]);
    });
});

describe('7. a manual discount at the till on top of a promotion', function () {
    it('caps the discount at what is left of the promotion price, never of the normal one', function () {
        pbrkPromo();
        $booking = pbrkInService(pbrkBook('2026-10-07 10:00'));
        $complete = fn (int $discount) => app(ChangeBookingStatus::class)->complete($booking, $this->owner, new Checkout($discount, ['cash' => 2800 - 560 - $discount]));

        // Price 28.00, deposit 5.60 paid: at most 22.40 off. 22.41 would fit under the normal S/ 35.
        expect(fn () => $complete(2241))->toThrow(PaymentException::class)
            ->and(fn () => $complete(-1))->toThrow(PaymentException::class);

        $done = $complete(500);

        expect([$done->price_cents, $done->promotion_cents, $done->discount_cents, $done->charged_cents])->toBe([2800, 700, 500, 2300])
            // 40 % of what was charged.
            ->and($done->commission_cents)->toBe(920)
            ->and((int) $done->payments()->withoutGlobalScopes()->sum('amount_cents'))->toBe(2300)
            ->and($done->promotion_id)->not->toBeNull();
    });

    it('lets the whole rest be discounted, and spreads a discount only over the lines that charged something', function () {
        pbrkGift();
        DB::table('service_staff')->where('staff_id', $this->staff->id)->where('service_id', $this->shave->id)->update(['commission_percent' => 100]);
        $booking = pbrkInService(pbrkBook('2026-10-07 10:00', services: [$this->shave, $this->cut]));

        $done = app(ChangeBookingStatus::class)->complete($booking, $this->owner, new Checkout(1000, ['yape' => 3500 - 700 - 1000]));

        // The gift line has nothing to discount and nothing to commission: 40 % of 25.00.
        expect([$done->charged_cents, $done->discount_cents, $done->promotion_cents, $done->commission_cents])->toBe([2500, 1000, 1200, 1000]);
    });
});

describe('4. dates and time zones', function () {
    it('covers the last local minute of its last day, although that is already tomorrow in UTC', function () {
        $night = Staff::factory()->for($this->salon)->working([5], '20:00', '23:45')->working([6], '00:00', '02:00')->create(['name' => 'Noche']);
        $night->services()->attach($this->shave->id);
        // Ends on Friday 9.
        pbrkPromo(['ends_on' => '2026-10-09', 'percent' => 50], [$this->shave]);

        // Friday 23:30 in Lima = Saturday 04:30 UTC.
        $last = pbrkBook('2026-10-09 23:30', services: [$this->shave], staff: $night);
        // Saturday 00:00 in Lima: the promotion is over.
        $after = pbrkBook('2026-10-10 00:00', SalonClient::factory()->for($this->salon)->create(), services: [$this->shave], staff: $night);

        expect($last->starts_at->utc()->toDateString())->toBe('2026-10-10')
            ->and($last->promotion_cents)->toBe(600)
            ->and($after->promotion_id)->toBeNull();
    });

    it('starts with the salon\'s day, not with the server\'s, east and west of UTC', function (string $timezone, string $first, string $before) {
        $this->salon->forceFill(['timezone' => $timezone, 'min_lead_minutes' => 0])->save();
        $always = Staff::factory()->for($this->salon)->working([1, 2, 3, 4, 5, 6, 7], '00:00', '23:45')->create();
        $always->services()->attach($this->cut->id);
        pbrkPromo(['starts_on' => '2026-10-09', 'ends_on' => '2026-10-09']);

        $inside = pbrkBook($first, staff: $always);
        $outside = pbrkBook($before, SalonClient::factory()->for($this->salon)->create(), staff: $always);

        expect($inside->promotion_cents)->toBe(700, "{$first} {$timezone} is {$inside->starts_at->utc()} UTC")
            ->and($outside->promotion_id)->toBeNull();
    })->with([
        // 00:15 on the 9th in Tokyo is still the 8th in UTC.
        'Tokyo, first minutes of the day' => ['Asia/Tokyo', '2026-10-09 00:15', '2026-10-08 23:00'],
        // 23:00 on the 9th in Lima is the 10th in UTC.
        'Lima, last hour of the day' => ['America/Lima', '2026-10-09 23:00', '2026-10-10 00:15'],
        'Kiritimati, UTC+14' => ['Pacific/Kiritimati', '2026-10-09 00:15', '2026-10-10 00:15'],
    ]);

    it('takes the weekday and the hours from the salon\'s clock at the very edges of the window', function () {
        // Wednesdays, 10:00 to 16:00.
        pbrkPromo(['weekdays' => [3], 'from_minute' => 600, 'to_minute' => 960]);
        $new = fn () => SalonClient::factory()->for($this->salon)->create();

        expect(pbrkBook('2026-10-07 09:30', $new())->promotion_id)->toBeNull()
            ->and(pbrkBook('2026-10-07 10:00', $new())->promotion_cents)->toBe(700)
            // Starts inside and ends after the window: the start counts.
            ->and(pbrkBook('2026-10-07 15:45', $new())->promotion_cents)->toBe(700)
            ->and(pbrkBook('2026-10-07 16:15', $new())->promotion_id)->toBeNull();
    });

    it('keeps a Wednesday-night promotion on Wednesday although UTC already says Thursday', function () {
        $night = Staff::factory()->for($this->salon)->working([3], '19:00', '23:45')->create();
        $night->services()->attach($this->cut->id);
        pbrkPromo(['weekdays' => [3], 'from_minute' => 19 * 60, 'to_minute' => 24 * 60]);

        $booking = pbrkBook('2026-10-07 23:15', staff: $night);

        expect($booking->starts_at->utc()->dayOfWeekIso)->toBe(4)->and($booking->promotion_cents)->toBe(700);
    });

    // BAJO. Nothing stops a window that crosses midnight (22:00 → 02:00) from
    // being stored, and PromotionTerms::covers then never matches: the
    // promotion is published and silently never applies.
    it('fixed: hours that cross midnight never match any visit', function () {
        $terms = new PromotionTerms(1, 'Trasnoche', PromotionMechanic::Discount, [1], '2026-10-05', '2026-10-18', fromMinute: 22 * 60, toMinute: 2 * 60, percent: 20);

        expect($terms->covers(pbrkAt('2026-10-07 23:00')))->toBeTrue();
    });

    it('picks the promotion that saves the most and, on a tie, the one that ends sooner', function () {
        $item = new QuoteItem(1, 'Corte', 3500, 30, 0, 0);
        $terms = fn (int $id, string $endsOn, ?int $percent, ?int $amount = null) => new PromotionTerms($id, "P{$id}", PromotionMechanic::Discount, [1], '2026-10-01', $endsOn, percent: $percent, amountCents: $amount);

        $ranked = PromotionPicker::ranked([$terms(1, '2026-10-30', 20), $terms(2, '2026-10-20', null, 700), $terms(3, '2026-10-25', 30), $terms(4, '2026-10-06', 70)], [$item], pbrkAt('2026-10-07 10:00'));

        // 4 ended yesterday; 3 saves most; 1 and 2 both save S/ 7 and 2 ends sooner.
        expect(array_map(fn (PromotionTerms $picked) => $picked->id, $ranked))->toBe([3, 2, 1]);
    });
});

describe('5. states', function () {
    it('books at the normal price when the promotion stopped between the quote and the booking', function (Closure $stop) {
        $promotion = pbrkPromo(['quota' => 5, 'once_per_client' => true]);
        $stop($promotion);

        $booking = pbrkBook('2026-10-07 10:00');

        expect($booking->promotion_id)->toBeNull()->and($booking->price_cents)->toBe(3500)->and(pbrkMoneyProblems($booking))->toBe([]);
    })->with([
        'paused' => [fn (Promotion $promotion) => $promotion->forceFill(['status' => PromotionStatus::Paused])->save()],
        'deleted' => [fn (Promotion $promotion) => $promotion->delete()],
        'ended yesterday' => [fn (Promotion $promotion) => $promotion->forceFill(['ends_on' => '2026-10-06'])->save()],
        'its only service switched off for the promotion, the booked one still on' => [function (Promotion $promotion) {
            test()->shave->forceFill(['is_active' => false])->save();
            $promotion->services()->attach(test()->shave->id);
        }],
        'gift switched off' => [function (Promotion $promotion) {
            $promotion->forceFill(['mechanic' => PromotionMechanic::Gift, 'percent' => null, 'gift_service_id' => test()->shave->id])->save();
            test()->shave->forceFill(['is_active' => false])->save();
        }],
    ]);

    // MEDIO. "Si un servicio incluido se desactiva, la promoción pasa a
    // «Revisar» y deja de ofrecerse." Deleting it is more than switching it
    // off, yet the promotion goes on applying: Promotion::services() skips
    // soft-deleted services, so Promotion::isIntact never sees the missing
    // one (and promotion_service keeps the row).
    it('fixed: goes on applying after one of its services is deleted from the catalogue', function () {
        pbrkPromo(services: [$this->cut, $this->shave]);
        $this->shave->delete();

        $booking = pbrkBook('2026-10-07 10:00');

        expect($booking->promotion_id)->toBeNull('the promotion names a service that no longer exists and still applied');
    });

    it('never applies a promotion of another salon, nor one of ours through a foreign service', function () {
        $other = app(CreateSalon::class)->handle(User::factory()->create(), 'Otro Salón', BusinessType::Unisex, Clientele::Everyone);
        $theirs = Service::factory()->for($other)->create(['price_cents' => 3500]);
        // Theirs, naming our service; and one of theirs that gives our shave away.
        pbrkPromo(['salon_id' => $other->id, 'percent' => 70], [$theirs, $this->cut]);
        pbrkGift(['salon_id' => $other->id], [$this->cut]);

        $booking = pbrkBook('2026-10-07 10:00', services: [$this->cut, $this->shave]);

        expect($booking->promotion_id)->toBeNull()->and($booking->price_cents)->toBe(4700);
    });

    it('charges the normal price when the team says so, and that booking takes no place of the promotion', function () {
        $promotion = pbrkPromo(['quota' => 1, 'once_per_client' => true]);

        $without = pbrkBook('2026-10-07 10:00', source: BookingSource::Staff, withPromotion: false);
        $with = pbrkBook('2026-10-07 11:00', source: BookingSource::Staff);

        expect($without->promotion_id)->toBeNull()->and($without->price_cents)->toBe(3500)->and($without->deposit_cents)->toBe(0)
            ->and($with->promotion_id)->toBe($promotion->id)->and($with->deposit_cents)->toBe(0)
            ->and(pbrkMoneyProblems($without))->toBe([]);
    });

    it('uses up "one per client" with a visit that happened or was missed, and gives it back on a hold that ran out', function () {
        $promotion = pbrkPromo(['once_per_client' => true]);
        $this->salon->forceFill(['min_lead_minutes' => 0])->save();

        // Its hold runs out: free again with no cron.
        $held = pbrkBook('2026-10-07 16:00');
        expect($held->status)->toBe(BookingStatus::Pending);
        $this->travelTo($held->hold_expires_at?->addSecond());
        $second = pbrkBook('2026-10-07 17:00');
        expect($second->promotion_id)->toBe($promotion->id)
            ->and(Booking::withoutGlobalScopes()->whereKey($held->id)->value('status'))->toBe(BookingStatus::Pending);

        // The second one is missed: used up.
        app(ChangeBookingStatus::class)->confirm($second, $this->owner);
        $this->travelTo(pbrkAt('2026-10-07 17:45'));
        app(ChangeBookingStatus::class)->markNoShow($second, $this->owner);

        expect(pbrkBook('2026-10-08 10:00')->promotion_id)->toBeNull();
    });

    // MEDIO. "Cupos: ocupa cupo toda reserva no cancelada […] al vencer su
    // retención lo libera." The freed place is taken by someone else, and
    // then the salon confirms the late deposit of the first one
    // (ChangeBookingStatus::confirm only checks that the time is still
    // free): two bookings hold the single place and both keep the price.
    // confirm() must re-count under the promotion's lock, or warn.
    it('refuses to confirm a hold that ran out once its promotion place was taken', function () {
        $promotion = pbrkPromo(['quota' => 1]);
        $this->salon->forceFill(['min_lead_minutes' => 0])->save();

        $late = pbrkBook('2026-10-07 16:00');
        $this->travelTo($late->hold_expires_at?->addMinute());
        $taker = pbrkBook('2026-10-07 17:00', SalonClient::factory()->for($this->salon)->create());
        expect($taker->promotion_id)->toBe($promotion->id);

        // The deposit arrives late and 16:00 is still free, but the place is not.
        expect(fn () => app(ChangeBookingStatus::class)->confirm($late, $this->owner))->toThrow(BookingException::class);

        expect($late->refresh()->status)->toBe(BookingStatus::Pending)
            ->and(Booking::withoutGlobalScopes()->where('promotion_id', $promotion->id)->holdingSlot()->count())->toBe(1);
    });

    // MEDIO. The same door for "una por cliente".
    it('refuses to confirm a late hold when the client already used their one promotion again', function () {
        $promotion = pbrkPromo(['once_per_client' => true]);
        $this->salon->forceFill(['min_lead_minutes' => 0])->save();

        $late = pbrkBook('2026-10-07 16:00');
        $this->travelTo($late->hold_expires_at?->addMinute());
        expect(pbrkBook('2026-10-07 17:00')->promotion_id)->toBe($promotion->id);

        expect(fn () => app(ChangeBookingStatus::class)->confirm($late, $this->owner))->toThrow(BookingException::class);

        expect(Booking::withoutGlobalScopes()->where('promotion_id', $promotion->id)->where('salon_client_id', $this->client->id)->holdingSlot()->count())->toBe(1);
    });

    it('falls back to the next best promotion when the best one is full', function () {
        $best = pbrkPromo(['percent' => 50, 'quota' => 1, 'name' => 'Mitad']);
        $next = pbrkPromo();

        expect(pbrkBook('2026-10-07 10:00')->promotion_id)->toBe($best->id);

        $second = pbrkBook('2026-10-07 11:00', SalonClient::factory()->for($this->salon)->create());

        expect($second->promotion_id)->toBe($next->id)->and($second->price_cents)->toBe(2800)->and(pbrkMoneyProblems($second))->toBe([]);
    });
});

describe('6. moving a booking that has a promotion', function () {
    beforeEach(function () {
        $this->salon->forceFill(['cancel_window_hours' => 0, 'min_lead_minutes' => 0, 'deposit_percent' => 0])->save();
        $this->move = app(RescheduleBooking::class);
    });

    it('lets the client move it to another day inside the promotion and refuses a day outside, leaving it where it was', function () {
        // Until Thursday 15.
        pbrkPromo(['ends_on' => '2026-10-15']);
        $booking = pbrkBook('2026-10-08 10:00');

        $moved = $this->move->handle(new BookingMove($booking, pbrkAt('2026-10-15 17:30'), byClient: true, actor: $this->maria));
        expect($moved->starts_at->getTimestamp())->toBe(pbrkAt('2026-10-15 17:30')->getTimestamp())->and($moved->price_cents)->toBe(2800);

        expect(fn () => $this->move->handle(new BookingMove($moved, pbrkAt('2026-10-16 10:00'), byClient: true, actor: $this->maria)))
            ->toThrow(BookingException::class)
            ->and($this->move->slots($moved, pbrkAt('2026-10-16 00:00'), byClient: true))->toBe([])
            ->and($moved->refresh()->starts_at->getTimestamp())->toBe(pbrkAt('2026-10-15 17:30')->getTimestamp())
            ->and(pbrkMoneyProblems($moved))->toBe([]);
    });

    it('neither frees nor takes a place when it moves, by the client or by the salon', function () {
        $promotion = pbrkPromo(['quota' => 1, 'once_per_client' => true]);
        $booking = pbrkBook('2026-10-08 10:00');
        $other = SalonClient::factory()->for($this->salon)->create();

        $booking = $this->move->handle(new BookingMove($booking, pbrkAt('2026-10-09 10:00'), byClient: true, actor: $this->maria));
        expect(pbrkBook('2026-10-08 10:00', $other)->promotion_id)->toBeNull();

        // The salon takes it out of the promotion's dates: it still holds the place.
        $booking = $this->move->handle(new BookingMove($booking, pbrkAt('2026-10-21 10:00'), byClient: false, actor: $this->owner));
        expect($booking->promotion_id)->toBe($promotion->id)->and($booking->price_cents)->toBe(2800)
            ->and(pbrkBook('2026-10-08 11:00', $other)->promotion_id)->toBeNull()
            ->and(pbrkBook('2026-10-08 12:00')->promotion_id)->toBeNull();
    });

    it('always lets the salon move it: promotion paused, ended, deleted or gone for good', function (Closure $change) {
        $promotion = pbrkPromo(['weekdays' => [3], 'from_minute' => 600, 'to_minute' => 960]);
        $booking = pbrkBook('2026-10-14 10:00');
        $change($promotion);

        expect($this->move->slots($booking->refresh(), pbrkAt('2026-10-16 00:00'), byClient: false))->not->toBe([]);

        $moved = $this->move->handle(new BookingMove($booking, pbrkAt('2026-10-16 17:00'), byClient: false, actor: $this->owner));

        expect($moved->price_cents)->toBe(2800)->and($moved->promotion_cents)->toBe(700)->and($moved->promotion_name)->toBe('20 % menos en Corte')
            ->and(pbrkMoneyProblems($moved->refresh()) === [] || $moved->promotion_id === null)->toBeTrue();
    })->with([
        'paused' => [fn (Promotion $promotion) => $promotion->forceFill(['status' => PromotionStatus::Paused])->save()],
        'ended' => [fn (Promotion $promotion) => $promotion->forceFill(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-06'])->save()],
        'deleted' => [fn (Promotion $promotion) => $promotion->delete()],
        'gone for good' => [fn (Promotion $promotion) => $promotion->forceDelete()],
    ]);

    it('keeps the client inside the promotion\'s terms after it was paused or deleted', function (Closure $change) {
        $promotion = pbrkPromo(['weekdays' => [3], 'from_minute' => 600, 'to_minute' => 960]);
        $booking = pbrkBook('2026-10-14 10:00');
        $change($promotion);
        $booking->refresh();

        expect(fn () => $this->move->handle(new BookingMove($booking, pbrkAt('2026-10-15 10:00'), byClient: true, actor: $this->maria)))->toThrow(BookingException::class)
            ->and($this->move->handle(new BookingMove($booking, pbrkAt('2026-10-14 15:45'), byClient: true, actor: $this->maria))->price_cents)->toBe(2800);
    })->with([
        'paused' => [fn (Promotion $promotion) => $promotion->forceFill(['status' => PromotionStatus::Paused])->save()],
        'deleted' => [fn (Promotion $promotion) => $promotion->delete()],
    ]);

    // BAJO. bookings.promotion_id is nullOnDelete: once the promotion row is
    // really gone the booking still says promotion_cents = 700 but
    // RescheduleBooking::keepsPromotion sees no promotion, so the client
    // carries the quiet-hours price to any day. keepsPromotion should look
    // at promotion_cents/promotion_name, not only at promotion_id.
    it('fixed: once the promotion is deleted for good the client carries its price to any day', function () {
        $promotion = pbrkPromo(['weekdays' => [3], 'from_minute' => 600, 'to_minute' => 960]);
        $booking = pbrkBook('2026-10-14 10:00');
        $promotion->forceDelete();
        $booking->refresh();

        expect($booking->promotion_cents)->toBe(700)
            ->and(fn () => $this->move->handle(new BookingMove($booking, pbrkAt('2026-10-16 17:00'), byClient: true, actor: $this->maria)))
            ->toThrow(BookingException::class);
    });

    it('8. offers a standard client no VIP hour when moving a booking with a promotion, and a VIP theirs', function () {
        $vipHours = Staff::factory()->for($this->salon)
            ->working([3], '09:00', '12:00')
            ->working([3], '12:00', '14:00', ScheduleAccess::Vip)
            ->working([3], '14:00', '18:00', ScheduleAccess::StaffOnly)
            ->create(['name' => 'Vip']);
        $vipHours->services()->attach($this->cut->id);
        pbrkPromo();
        $vip = SalonClient::factory()->for($this->salon)->create(['is_vip' => true, 'user_id' => User::factory()->create()->id]);
        $standard = pbrkBook('2026-10-14 09:00', staff: $vipHours);
        $privileged = pbrkBook('2026-10-14 10:00', $vip, staff: $vipHours);
        $hours = fn (Booking $booking, bool $byClient): array => array_map(
            fn (int $start): string => CarbonImmutable::createFromTimestamp($start, 'America/Lima')->format('H:i'),
            $this->move->slots($booking, pbrkAt('2026-10-14 00:00'), $byClient),
        );

        expect($hours($standard, true))->toContain('11:00')->not->toContain('11:45')->not->toContain('12:00')->not->toContain('13:30')->not->toContain('14:00')
            ->and($hours($privileged, true))->toContain('12:00')->toContain('13:30')->not->toContain('13:45')->not->toContain('14:00')
            ->and($hours($standard, false))->toContain('12:00')->toContain('17:30');

        expect(fn () => $this->move->handle(new BookingMove($standard, pbrkAt('2026-10-14 12:30'), byClient: true, actor: $this->maria)))
            ->toThrow(BookingException::class);
    });
});
