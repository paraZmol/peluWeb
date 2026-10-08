<?php

namespace App\Data\Salon;

use App\Enums\SalonTheme;

/**
 * How whoever manages a salon wants to see its public page before saving:
 * with another template, and with sample photos where their own are still
 * missing. Never built from a visitor's request.
 */
final readonly class SalonPreview
{
    public function __construct(
        public SalonTheme $theme,
        public bool $withSamples,
    ) {}
}
