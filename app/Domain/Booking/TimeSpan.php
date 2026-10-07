<?php

namespace App\Domain\Booking;

use InvalidArgumentException;

/**
 * A stretch of absolute time as Unix timestamps (seconds, so always UTC).
 * The end is exclusive: spans that only touch do not overlap.
 */
final readonly class TimeSpan
{
    public function __construct(public int $start, public int $end)
    {
        if ($start >= $end) {
            throw new InvalidArgumentException("Invalid time span {$start}-{$end}.");
        }
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function covers(self $other): bool
    {
        return $this->start <= $other->start && $other->end <= $this->end;
    }
}
