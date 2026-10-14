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
     * @param  int|null  $chosenStaffId  the professional the person chose; null for "anyone"
     */
    public function __construct(public array $items, public ?PromotionTerms $promotion = null, public ?int $chosenStaffId = null) {}

    /**
     * The same quote, saying whom the person chose: a promotion of some of
     * the team holds only then.
     */
    public function chosenWith(?int $staffId): self
    {
        return new self($this->items, $this->promotion, $staffId);
    }

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

        return new self($promotion->price($normal), $promotion, $this->chosenStaffId);
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
