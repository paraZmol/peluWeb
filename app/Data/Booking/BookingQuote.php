<?php

namespace App\Data\Booking;

use App\Domain\Booking\BookingShape;
use App\Domain\Promotions\PromotionTerms;

/**
 * What a selection of services costs and how long it takes with one
 * specific professional.
 */
final readonly class BookingQuote
{
    /**
     * @param  list<QuoteItem>  $items
     * @param  PromotionTerms|null  $promotion  the one already applied to the prices of $items
     */
    public function __construct(public array $items, public ?PromotionTerms $promotion = null) {}

    /**
     * What the client pays, any promotion already taken off.
     */
    public function priceCents(): int
    {
        return array_sum(array_map(fn (QuoteItem $item): int => $item->priceCents, $this->items));
    }

    /**
     * What the promotion saved; 0 without one.
     */
    public function promotionCents(): int
    {
        return array_sum(array_map(fn (QuoteItem $item): int => $item->normalPriceCents() - $item->priceCents, $this->items));
    }

    /**
     * The same selection priced under a promotion. A quote takes one: a
     * second call replaces the first, from the normal prices.
     */
    public function withPromotion(PromotionTerms $promotion): self
    {
        $normal = array_map(fn (QuoteItem $item): QuoteItem => $item->chargedAt($item->normalPriceCents()), $this->items);

        return new self($promotion->price($normal), $promotion);
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
