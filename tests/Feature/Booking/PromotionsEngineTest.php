<?php

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\RescheduleBooking;
use App\Actions\Salon\CreateSalon;
use App\Data\Booking\BookingMove;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\BusinessType;
use App\Enums\Clientele;
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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/*
| Step 6.7 (a): a promotion applies by itself when a booking qualifies and
| its price stays frozen on the booking (docs/promociones.md). Estudio Luz
| asks a 20 % deposit; Ana works Wednesday to Friday, 9 to 18. Corte costs
| S/ 35, Rasurado S/ 12. "Now" is Wednesday 2026-10-07 08:00 in Lima.
*/

/**
 * @param  array<string, mixed>  $with
 * @param  list<Service>|null  $services
 */
function promo(array $with = [], ?array $services = null): Promotion
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
 * @param  list<Service>|null  $services
 */
function promoBooking(string $at, ?SalonClient $client = null, ?array $services = null, BookingSource $source = BookingSource::Client, bool $withPromotion = true): Booking
{
    return app(CreateBooking::class)->handle(new NewBooking(
        salon: test()->salon->refresh(),
        client: $client ?? test()->client,
        selections: array_map(fn (Service $service): array => ['service_id' => $service->id, 'variant_id' => null], $services ?? [test()->cut]),
        staff: test()->staff,
        startsAt: CarbonImmutable::parse($at, 'America/Lima'),
        source: $source,
        withPromotion: $withPromotion,
    ));
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-10-07 08:00', 'America/Lima'));

    $this->owner = User::factory()->create();
    $this->salon = app(CreateSalon::class)->handle($this->owner, 'Estudio Luz', BusinessType::Unisex, Clientele::Everyone);
    $this->salon->forceFill(['timezone' => 'America/Lima', 'deposit_percent' => 20])->save();
    $this->staff = Staff::factory()->for($this->salon)->working([3, 4, 5], '09:00', '18:00')->create(['name' => 'Ana']);
    $this->cut = Service::factory()->for($this->salon)->create(['name' => 'Corte', 'price_cents' => 3500, 'active_minutes' => 30]);
    $this->shave = Service::factory()->for($this->salon)->create(['name' => 'Rasurado', 'price_cents' => 1200, 'active_minutes' => 15]);
    $this->staff->services()->attach([$this->cut->id, $this->shave->id]);

    $this->maria = User::factory()->create();
    $this->client = SalonClient::factory()->for($this->salon)->create(['user_id' => $this->maria->id]);
});

it('prices the booking under the promotion and freezes it there', function () {
    $promotion = promo();

    $booking = promoBooking('2026-10-07 10:00');
    $line = BookingService::query()->where('booking_id', $booking->id)->sole();

    expect($booking->price_cents)->toBe(2800)
        ->and($booking->promotion_id)->toBe($promotion->id)
        ->and($booking->promotion_name)->toBe('20 % menos en Corte')
        ->and($booking->promotion_cents)->toBe(700)
        ->and($line->price_cents)->toBe(2800)
        ->and($line->list_price_cents)->toBe(3500)
        // The deposit is the salon's percentage of what is actually paid.
        ->and($booking->deposit_cents)->toBe(560);

    // Changing or ending the promotion later does not touch the booking.
    $promotion->forceFill(['percent' => 50, 'status' => PromotionStatus::Paused])->save();
    expect($booking->refresh()->price_cents)->toBe(2800);

    // Completed: what is charged, and the commission on it, follow the frozen price.
    app(ChangeBookingStatus::class)->confirm($booking, $this->owner);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Lima'));
    app(ChangeBookingStatus::class)->start($booking, $this->owner);
    $done = app(ChangeBookingStatus::class)->complete($booking, $this->owner);
    expect($done->charged_cents)->toBe(2800)->and($done->discount_cents)->toBe(0)->and($done->promotion_cents)->toBe(700);
});

it('applies nothing while it is a draft, paused, out of its dates or missing a service', function (Closure $arrange) {
    $promotion = promo();
    $arrange($promotion);

    $booking = promoBooking('2026-10-07 10:00');

    expect($booking->price_cents)->toBe(3500)->and($booking->promotion_id)->toBeNull()->and($booking->promotion_cents)->toBe(0)
        ->and(BookingService::query()->where('booking_id', $booking->id)->sole()->list_price_cents)->toBe(3500);
})->with([
    'draft' => [fn (Promotion $promotion) => $promotion->forceFill(['status' => PromotionStatus::Draft])->save()],
    'paused' => [fn (Promotion $promotion) => $promotion->forceFill(['status' => PromotionStatus::Paused])->save()],
    'starts tomorrow' => [fn (Promotion $promotion) => $promotion->forceFill(['starts_on' => '2026-10-08'])->save()],
    'ended yesterday' => [fn (Promotion $promotion) => $promotion->forceFill(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-06'])->save()],
    'deleted' => [fn (Promotion $promotion) => $promotion->delete()],
    'one of its services is no longer offered' => [function (Promotion $promotion) {
        // Another service it names is no longer offered: it waits for the owner.
        $gone = Service::factory()->for(test()->salon)->create(['is_active' => false]);
        $promotion->services()->attach($gone->id);
    }],
    'for another service' => [fn (Promotion $promotion) => $promotion->services()->sync([test()->shave->id])],
]);

it('follows the date and the start time of the visit, on the salon\'s clock', function () {
    promo(['weekdays' => [3], 'from_minute' => 600, 'to_minute' => 960]);

    expect(promoBooking('2026-10-07 10:00')->promotion_cents)->toBe(700)
        ->and(promoBooking('2026-10-07 16:00')->promotion_cents)->toBe(0)
        ->and(promoBooking('2026-10-08 10:00')->promotion_cents)->toBe(0)
        // By the visit's date, not by the day it is booked on.
        ->and(promoBooking('2026-10-14 11:00')->promotion_cents)->toBe(700);
});

it('gives the gift for free only with a service that earns it', function () {
    promo(['mechanic' => PromotionMechanic::Gift, 'card' => 'gift', 'name' => 'Rasurado gratis con tu Corte', 'percent' => null, 'gift_service_id' => $this->shave->id]);

    $both = promoBooking('2026-10-07 10:00', services: [$this->cut, $this->shave]);
    $lines = BookingService::query()->where('booking_id', $both->id)->orderBy('position')->get();

    expect($both->price_cents)->toBe(3500)->and($both->promotion_cents)->toBe(1200)
        ->and($lines[1]->price_cents)->toBe(0)->and($lines[1]->list_price_cents)->toBe(1200)
        // The time of the gift still counts.
        ->and($both->ends_at->diffInMinutes($both->starts_at, true))->toBe(45.0)
        ->and(promoBooking('2026-10-07 11:00', services: [$this->cut])->promotion_id)->toBeNull();
});

it('applies one promotion per booking: the one that saves the most', function () {
    promo(['name' => 'Diez']);
    $better = promo(['name' => 'S/ 12 menos', 'percent' => null, 'amount_cents' => 1200]);

    $booking = promoBooking('2026-10-07 10:00');

    expect($booking->promotion_id)->toBe($better->id)->and($booking->price_cents)->toBe(2300)->and($booking->promotion_cents)->toBe(1200);
});

it('counts its places: a cancelled booking or a hold that ran out gives one back', function () {
    $promotion = promo(['quota' => 1]);
    $other = SalonClient::factory()->for($this->salon)->create();

    $first = promoBooking('2026-10-07 10:00');
    expect($first->promotion_id)->toBe($promotion->id)
        // No place left: booked all the same, at the normal price.
        ->and(promoBooking('2026-10-07 11:00', $other)->promotion_id)->toBeNull();

    app(ChangeBookingStatus::class)->cancel($first, $this->owner, byClient: false);
    $second = promoBooking('2026-10-07 12:00', $other);
    expect($second->promotion_id)->toBe($promotion->id)->and($second->status)->toBe(BookingStatus::Pending);

    // Its deposit never came: the place is free again without waiting for the cron.
    $this->travelTo($second->hold_expires_at?->addMinute());
    expect(promoBooking('2026-10-07 14:00')->promotion_id)->toBe($promotion->id);
});

it('gives it once per client when it says so, and a visit that happened uses it up', function () {
    $promotion = promo(['once_per_client' => true]);
    $other = SalonClient::factory()->for($this->salon)->create();

    $first = promoBooking('2026-10-07 10:00');
    expect($first->promotion_id)->toBe($promotion->id)
        ->and(promoBooking('2026-10-07 11:00')->promotion_id)->toBeNull()
        ->and(promoBooking('2026-10-07 12:00', $other)->promotion_id)->toBe($promotion->id);

    // Cancelling gives it back to them.
    app(ChangeBookingStatus::class)->cancel($first, $this->owner, byClient: false);
    expect(promoBooking('2026-10-07 13:00')->promotion_id)->toBe($promotion->id);
});

it('lets the team book at the normal price on purpose, with no deposit either way', function () {
    promo();

    $with = promoBooking('2026-10-07 10:00', source: BookingSource::Staff);
    $without = promoBooking('2026-10-07 11:00', source: BookingSource::Staff, withPromotion: false);

    expect($with->price_cents)->toBe(2800)->and($with->deposit_cents)->toBe(0)
        ->and($without->price_cents)->toBe(3500)->and($without->promotion_id)->toBeNull();
});

it('lets a client move it only to where the promotion still applies; the salon, anywhere', function () {
    promo(['weekdays' => [3], 'from_minute' => 600, 'to_minute' => 960]);
    $this->salon->forceFill(['cancel_window_hours' => 0, 'min_lead_minutes' => 0, 'deposit_percent' => 0])->save();
    $booking = promoBooking('2026-10-14 10:00');
    $move = app(RescheduleBooking::class);
    $at = fn (string $time) => CarbonImmutable::parse($time, 'America/Lima');

    // The times offered to the client leave out the ones without the promotion.
    $offered = $move->slots($booking, $at('2026-10-14 00:00'), byClient: true);
    expect($offered)->toContain($at('2026-10-14 11:00')->getTimestamp())->not->toContain($at('2026-10-14 16:30')->getTimestamp())
        ->and($move->slots($booking, $at('2026-10-15 00:00'), byClient: true))->toBe([])
        ->and($move->slots($booking, $at('2026-10-15 00:00'), byClient: false))->not->toBe([]);

    expect(fn () => $move->handle(new BookingMove($booking, $at('2026-10-14 17:00'), byClient: true, actor: $this->maria)))
        ->toThrow(BookingException::class);

    $moved = $move->handle(new BookingMove($booking, $at('2026-10-14 11:00'), byClient: true, actor: $this->maria));
    expect($moved->price_cents)->toBe(2800);

    // The salon moves it out of the promotion's hours: the price stays.
    $moved = $move->handle(new BookingMove($moved, $at('2026-10-15 17:00'), byClient: false, actor: $this->owner));
    expect($moved->price_cents)->toBe(2800)->and($moved->promotion_cents)->toBe(700);
});

it('never applies another salon\'s promotion', function () {
    $otherOwner = User::factory()->create();
    $other = app(CreateSalon::class)->handle($otherOwner, 'Otro Salón', BusinessType::Unisex, Clientele::Everyone);
    $theirs = Service::factory()->for($other)->create();
    promo(['salon_id' => $other->id], [$theirs, $this->cut]);

    expect(promoBooking('2026-10-07 10:00')->promotion_id)->toBeNull();
});
