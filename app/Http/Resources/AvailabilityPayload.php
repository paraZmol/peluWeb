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
     * @return list<array{start: int, time: string}>
     */
    public static function slots(array $starts, Salon $salon): array
    {
        return array_map(fn (int $start): array => [
            'start' => $start,
            'time' => CarbonImmutable::createFromTimestamp($start, $salon->timezone)->format('H:i'),
        ], $starts);
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
