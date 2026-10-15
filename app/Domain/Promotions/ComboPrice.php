<?php

namespace App\Domain\Promotions;

/**
 * What a combo is announced at: its services together at the lowest price
 * someone can actually book them for, less its saving.
 */
final readonly class ComboPrice
{
    /**
     * @param  int  $normalCents  the services one after the other, with the cheapest professional who does them all
     * @param  bool  $isFrom  an option or a professional may change it: it is said with "desde"
     * @param  bool  $isBookable  some active professional does every service of it
     */
    public function __construct(
        public int $normalCents,
        public int $savingCents,
        public bool $isFrom,
        public bool $isBookable = true,
    ) {}

    public function priceCents(): int
    {
        return $this->normalCents - $this->savingCents;
    }
}
