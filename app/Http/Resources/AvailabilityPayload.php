<?php

namespace App\Http\Resources;

use App\Data\Booking\BookingQuote;
use App\Models\Salon;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * The JSON every time picker reads: free starts on the salon's clock and,
 * when services are being chosen, what they cost.
 */
final class AvailabilityPayload
{
    /**
     * @param  list<int>  $starts  Unix seconds (UTC)
     * @param  array<int, array{name: string, cents: int}>  $promotions  by start, where one applies
     * @return list<array{start: int, time: string, promotion: array{name: string, saves: string}|null}>
     */
    public static function slots(array $starts, Salon $salon, array $promotions = []): array
    {
        return array_map(fn (int $start): array => [
            'start' => $start,
            'time' => CarbonImmutable::createFromTimestamp($start, $salon->timezone)->format('H:i'),
            // Booking at this time gets it: the picker marks it "con promo".
            'promotion' => isset($promotions[$start])
                ? ['name' => $promotions[$start]['name'], 'saves' => Money::toAmount($promotions[$start]['cents'])]
                : null,
        ], $starts);
    }

    /**
     * The quote the "from" price comes from: the cheapest professional's.
     *
     * @param  array<int, BookingQuote>  $quotes
     */
    public static function cheapest(array $quotes): ?BookingQuote
    {
        $cheapest = null;

        foreach ($quotes as $quote) {
            $cheapest = $cheapest === null || $quote->priceCents() < $cheapest->priceCents() ? $quote : $cheapest;
        }

        return $cheapest;
    }

    /**
     * Price, length and deposit of a selection. With "anyone" the price
     * may depend on who attends: the lowest is shown as "from".
     *
     * @param  array<int, BookingQuote>  $quotes
     * @return array<string, mixed>|null
     */
    public static function quote(array $quotes, int $depositPercent): ?array
    {
        if ($quotes === []) {
            return null;
        }

        $prices = array_map(fn (BookingQuote $quote): int => $quote->priceCents(), $quotes);
        $minutes = array_map(fn (BookingQuote $quote): int => $quote->shape()->totalMinutes, $quotes);

        return [
            'price' => Money::toAmount(min($prices)),
            'priceVaries' => min($prices) !== max($prices),
            'minutes' => min($minutes),
            'minutesVary' => min($minutes) !== max($minutes),
            'depositPercent' => $depositPercent,
            'deposit' => Money::toAmount(intdiv(min($prices) * $depositPercent + 50, 100)),
        ];
    }
}
