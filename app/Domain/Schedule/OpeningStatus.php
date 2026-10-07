<?php

namespace App\Domain\Schedule;

/**
 * Whether a salon is open at some instant and when that changes.
 */
final readonly class OpeningStatus
{
    /**
     * @param  string|null  $time  "HH:MM": closing time when open, next opening time when closed
     * @param  int|null  $daysAhead  days until the next opening (0 = later today); null when open or unknown
     * @param  int|null  $weekday  ISO weekday of the next opening
     */
    public function __construct(
        public bool $isOpen,
        public ?string $time = null,
        public ?int $daysAhead = null,
        public ?int $weekday = null,
    ) {}
}
