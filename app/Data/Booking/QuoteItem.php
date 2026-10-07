<?php

namespace App\Data\Booking;

/**
 * One service as a given professional would perform it: their own working
 * time and price already applied.
 */
final readonly class QuoteItem
{
    public function __construct(
        public int $serviceId,
        public string $name,
        public int $priceCents,
        public int $activeMinutes,
        public int $idleMinutes,
        public int $finishMinutes,
    ) {}
}
