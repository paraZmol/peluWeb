<?php

namespace App\Domain\Schedule;

use InvalidArgumentException;

/**
 * A range of wall-clock time inside one day, in minutes from midnight.
 * The end is exclusive: ranges that only touch do not overlap.
 */
final readonly class TimeRange
{
    private const int MINUTES_PER_DAY = 1440;

    public function __construct(public int $startMinute, public int $endMinute)
    {
        if ($startMinute < 0 || $endMinute > self::MINUTES_PER_DAY || $startMinute >= $endMinute) {
            throw new InvalidArgumentException("Invalid time range {$startMinute}-{$endMinute}.");
        }
    }

    /**
     * @param  string  $start  "HH:MM" (seconds, if any, are ignored)
     * @param  string  $end  "HH:MM"
     */
    public static function fromStrings(string $start, string $end): self
    {
        return new self(self::toMinutes($start), self::toMinutes($end));
    }

    public static function toMinutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    public static function toTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function overlaps(self $other): bool
    {
        return $this->startMinute < $other->endMinute && $other->startMinute < $this->endMinute;
    }

    public function contains(int $minute): bool
    {
        return $minute >= $this->startMinute && $minute < $this->endMinute;
    }

    public function minutes(): int
    {
        return $this->endMinute - $this->startMinute;
    }

    public function start(): string
    {
        return self::toTime($this->startMinute);
    }

    public function end(): string
    {
        return self::toTime($this->endMinute);
    }
}
