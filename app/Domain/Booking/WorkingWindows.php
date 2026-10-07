<?php

namespace App\Domain\Booking;

use App\Enums\ScheduleAccess;
use Carbon\CarbonImmutable;

/**
 * Turns a professional's weekly hours (wall-clock, salon timezone) into the
 * absolute windows of one calendar day that a given kind of client may
 * book. Pure: receives everything it needs.
 */
final class WorkingWindows
{
    /**
     * @param  CarbonImmutable  $localDate  any instant of the day, in the salon's timezone
     * @param  iterable<array{weekday: int, start: string, end: string, access: ScheduleAccess}>  $ranges  times as "HH:MM"
     * @param  list<ScheduleAccess>  $allowed  access levels this client may book
     * @return list<TimeSpan> sorted; ranges that touch are joined (public 9–13 + VIP 13–15 is one window for a VIP)
     */
    public static function forDate(CarbonImmutable $localDate, iterable $ranges, array $allowed): array
    {
        $spans = [];

        foreach ($ranges as $range) {
            if ($range['weekday'] !== $localDate->dayOfWeekIso || ! in_array($range['access'], $allowed, true)) {
                continue;
            }

            $start = $localDate->setTimeFromTimeString($range['start'])->getTimestamp();
            $end = $localDate->setTimeFromTimeString($range['end'])->getTimestamp();

            // A range swallowed by a daylight-saving jump has no length that day.
            if ($start < $end) {
                $spans[] = new TimeSpan($start, $end);
            }
        }

        return self::merge($spans);
    }

    /**
     * @param  list<TimeSpan>  $spans
     * @return list<TimeSpan>
     */
    private static function merge(array $spans): array
    {
        usort($spans, fn (TimeSpan $a, TimeSpan $b) => $a->start <=> $b->start);
        $merged = [];
        $open = null;

        foreach ($spans as $span) {
            if ($open !== null && $span->start <= $open->end) {
                $open = new TimeSpan($open->start, max($open->end, $span->end));

                continue;
            }

            if ($open !== null) {
                $merged[] = $open;
            }

            $open = $span;
        }

        if ($open !== null) {
            $merged[] = $open;
        }

        return $merged;
    }
}
