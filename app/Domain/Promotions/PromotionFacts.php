<?php

namespace App\Domain\Promotions;

/**
 * What the fine print of a promotion says besides its terms: whose it is
 * and the limits the salon set on it.
 */
final readonly class PromotionFacts
{
    /**
     * @param  int|null  $quota  bookings it takes; null = no limit
     * @param  bool  $hasDeposit  the salon asks for a deposit: the price is held until it is due
     * @param  list<string>  $staffNames  only with these professionals; empty = all of the team
     */
    public function __construct(
        public string $salonName,
        public ?int $quota = null,
        public bool $oncePerClient = false,
        public ?string $giftName = null,
        public ?int $giftPriceCents = null,
        public ?int $giftMinutes = null,
        public bool $hasDeposit = false,
        public array $staffNames = [],
    ) {}
}
