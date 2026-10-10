<?php

namespace App\Domain\Accounting;

use App\Domain\Booking\TimeSpan;

/**
 * How long a professional was really there to attend: their working
 * windows minus the time off that fell inside them. Pure.
 */
final class OpenTime
{
    /**
     * @param  list<TimeSpan>  $windows  working windows that do not overlap each other
     * @param  list<TimeSpan>  $blocked  time off, in any order; may overlap
     */
    public static function seconds(array $windows, array $blocked): int
    {
        usort($blocked, fn (TimeSpan $a, TimeSpan $b): int => $a->start <=> $b->start);
        $open = 0;

        foreach ($windows as $window) {
            $open += $window->end - $window->start - self::covered($window, $blocked);
        }

        return $open;
    }

    /**
     * Seconds of the window taken by the blocks, counting once what two
     * blocks share.
     *
     * @param  list<TimeSpan>  $blocked  sorted by start
     */
    private static function covered(TimeSpan $window, array $blocked): int
    {
        $covered = 0;
        $cursor = $window->start;

        foreach ($blocked as $block) {
            $start = max($block->start, $cursor);
            $end = min($block->end, $window->end);

            if ($start < $end) {
                $covered += $end - $start;
                $cursor = $end;
            }
        }

        return $covered;
    }
}
