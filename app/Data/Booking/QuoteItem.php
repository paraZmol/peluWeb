<?php

namespace App\Data\Booking;

/**
 * One service as a given professional would perform it: their own working
 * time and price already applied.
 */
final readonly class QuoteItem
{
    /**
     * @param  int  $priceCents  what is charged for it
     * @param  int|null  $listPriceCents  its normal price, when a promotion changed what is charged; null = the same
     */
    public function __construct(
        public int $serviceId,
        public string $name,
        public int $priceCents,
        public int $activeMinutes,
        public int $idleMinutes,
        public int $finishMinutes,
        public ?int $variantId = null,
        public ?int $listPriceCents = null,
    ) {}

    public function normalPriceCents(): int
    {
        return $this->listPriceCents ?? $this->priceCents;
    }

    /**
     * The same service charged at another price; its normal price is kept.
     */
    public function chargedAt(int $priceCents): self
    {
        return new self(
            $this->serviceId,
            $this->name,
            $priceCents,
            $this->activeMinutes,
            $this->idleMinutes,
            $this->finishMinutes,
            $this->variantId,
            $this->normalPriceCents(),
        );
    }
}
