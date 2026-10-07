<?php

namespace App\Data\Booking;

use App\Domain\Booking\BookingShape;

/**
 * What a selection of services costs and how long it takes with one
 * specific professional.
 */
final readonly class BookingQuote
{
    /**
     * @param  list<QuoteItem>  $items
     */
    public function __construct(public array $items) {}

    public function priceCents(): int
    {
        return array_sum(array_map(fn (QuoteItem $item): int => $item->priceCents, $this->items));
    }

    public function shape(): BookingShape
    {
        return BookingShape::fromServices(array_map(fn (QuoteItem $item): array => [
            'active' => $item->activeMinutes,
            'idle' => $item->idleMinutes,
            'finish' => $item->finishMinutes,
        ], $this->items));
    }
}
