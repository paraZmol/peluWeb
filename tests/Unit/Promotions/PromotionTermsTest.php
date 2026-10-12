<?php

use App\Data\Booking\BookingQuote;
use App\Data\Booking\QuoteItem;
use App\Domain\Promotions\PromotionPicker;
use App\Domain\Promotions\PromotionTerms;
use App\Enums\PromotionMechanic;
use Carbon\CarbonImmutable;

/*
| The rules of docs/promociones.md on the pure classes. Services: 1 = Corte
| (S/ 35), 2 = Barba (S/ 18.50), 3 = Rasurado (S/ 12). Wednesday 2026-10-07
| in Lima is the reference day.
*/

function promoLine(int $serviceId, int $priceCents): QuoteItem
{
    return new QuoteItem($serviceId, "Servicio {$serviceId}", $priceCents, 30, 0, 0);
}

/**
 * @param  array<string, mixed>  $with
 */
function promoTerms(PromotionMechanic $mechanic, array $with = []): PromotionTerms
{
    return new PromotionTerms(...[
        'id' => 1,
        'name' => 'Promo',
        'mechanic' => $mechanic,
        'serviceIds' => [1],
        'startsOn' => '2026-10-05',
        'endsOn' => '2026-10-18',
        ...$with,
    ]);
}

function limaAt(string $at): CarbonImmutable
{
    return CarbonImmutable::parse($at, 'America/Lima');
}

/**
 * @param  list<QuoteItem>  $items
 * @return list<int>
 */
function chargedCents(array $items): array
{
    return array_map(fn (QuoteItem $item): int => $item->priceCents, $items);
}

it('takes the percentage off each included service, down to 10 céntimos', function () {
    $terms = promoTerms(PromotionMechanic::Discount, ['percent' => 15, 'serviceIds' => [1, 2]]);
    $items = [promoLine(1, 3500), promoLine(2, 1850), promoLine(3, 1200)];

    // 35.00 → 29.75 → 29.70; 18.50 → 15.725 → 15.70; the third is not included.
    expect(chargedCents($terms->price($items)))->toBe([2970, 1570, 1200])
        ->and($terms->savedCents($items))->toBe(530 + 280)
        ->and($terms->price($items)[0]->normalPriceCents())->toBe(3500);
});

it('takes a fixed amount off once per booking, never below zero', function () {
    $terms = promoTerms(PromotionMechanic::Discount, ['amountCents' => 1000, 'serviceIds' => [1, 2]]);

    expect(chargedCents($terms->price([promoLine(1, 3500), promoLine(2, 1850)])))->toBe([2500, 1850])
        ->and(chargedCents($terms->price([promoLine(3, 1200), promoLine(2, 1850)])))->toBe([1200, 850])
        ->and(chargedCents($terms->price([promoLine(2, 800)])))->toBe([0])
        ->and($terms->savedCents([promoLine(3, 1200)]))->toBe(0);
});

it('gives the gift only together with a service that earns it', function () {
    $terms = promoTerms(PromotionMechanic::Gift, ['serviceIds' => [1], 'giftServiceId' => 3]);

    expect(chargedCents($terms->price([promoLine(1, 3500), promoLine(3, 1200)])))->toBe([3500, 0])
        ->and($terms->savedCents([promoLine(1, 3500), promoLine(3, 1200)]))->toBe(1200)
        // Alone, or with something that does not earn it, it costs what it costs.
        ->and($terms->savedCents([promoLine(3, 1200)]))->toBe(0)
        ->and($terms->savedCents([promoLine(2, 1850), promoLine(3, 1200)]))->toBe(0)
        // Earning it without adding it saves nothing.
        ->and($terms->savedCents([promoLine(1, 3500)]))->toBe(0);
});

it('covers a visit by its date, and by its weekday and start time when it names them', function (string $at, bool $covered) {
    $terms = promoTerms(PromotionMechanic::Discount, ['percent' => 20, 'weekdays' => [1, 2, 3], 'fromMinute' => 600, 'toMinute' => 960]);

    expect($terms->covers(limaAt($at)))->toBe($covered);
})->with([
    'inside' => ['2026-10-07 10:00', true],
    'last minute of the window' => ['2026-10-07 15:59', true],
    'the window is closed at its end' => ['2026-10-07 16:00', false],
    'too early' => ['2026-10-07 09:45', false],
    'a Thursday' => ['2026-10-08 11:00', false],
    'the day before it starts' => ['2026-09-30 11:00', false],
    'last day' => ['2026-10-14 11:00', true],
    'the day after it ends' => ['2026-10-19 11:00', false],
]);

it('covers every day and hour when it names none', function () {
    $terms = promoTerms(PromotionMechanic::Discount, ['percent' => 20]);

    expect($terms->covers(limaAt('2026-10-18 23:55')))->toBeTrue()
        ->and($terms->covers(limaAt('2026-10-05 00:00')))->toBeTrue();
});

it('picks the promotion that saves the most; on a tie, the one that ends sooner', function () {
    $small = promoTerms(PromotionMechanic::Discount, ['id' => 1, 'percent' => 10]);
    $big = promoTerms(PromotionMechanic::Discount, ['id' => 2, 'percent' => 30]);
    $sameButSooner = promoTerms(PromotionMechanic::Discount, ['id' => 3, 'percent' => 30, 'endsOn' => '2026-10-10']);
    $elsewhere = promoTerms(PromotionMechanic::Discount, ['id' => 4, 'percent' => 50, 'serviceIds' => [9]]);
    $notToday = promoTerms(PromotionMechanic::Discount, ['id' => 5, 'percent' => 60, 'weekdays' => [5]]);
    $items = [promoLine(1, 3500)];

    $ranked = PromotionPicker::ranked([$small, $big, $sameButSooner, $elsewhere, $notToday], $items, limaAt('2026-10-07 10:00'));

    expect(array_map(fn (PromotionTerms $terms): int => $terms->id, $ranked))->toBe([3, 2, 1]);
});

it('prices a quote under one promotion at a time, from the normal prices', function () {
    $quote = new BookingQuote([promoLine(1, 3500), promoLine(3, 1200)]);
    $twenty = promoTerms(PromotionMechanic::Discount, ['percent' => 20]);
    $gift = promoTerms(PromotionMechanic::Gift, ['id' => 2, 'giftServiceId' => 3]);

    $discounted = $quote->withPromotion($twenty);
    expect($discounted->priceCents())->toBe(2800 + 1200)->and($discounted->promotionCents())->toBe(700);

    // Replacing it does not pile one on the other.
    $gifted = $discounted->withPromotion($gift);
    expect($gifted->priceCents())->toBe(3500)->and($gifted->promotionCents())->toBe(1200)->and($gifted->promotion?->id)->toBe(2)
        ->and($quote->promotionCents())->toBe(0);
});
