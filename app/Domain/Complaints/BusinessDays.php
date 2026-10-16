<?php

namespace App\Domain\Complaints;

use Carbon\CarbonImmutable;

/**
 * Business days in Peru: Monday to Friday, minus holidays. Works on local
 * calendar dates (no time of day), so the caller converts first.
 */
final readonly class BusinessDays
{
    /**
     * @param  list<string>  $holidays  "MM-DD" every year, or "YYYY-MM-DD" once
     */
    public function __construct(private array $holidays) {}

    /**
     * The day the deadline ends: the n-th business day after $from. The day
     * something was received does not count, even if it is a business day.
     */
    public function addTo(CarbonImmutable $from, int $days): CarbonImmutable
    {
        $date = $from->startOfDay();

        while ($days > 0) {
            $date = $date->addDay();

            if ($this->isBusinessDay($date)) {
                $days--;
            }
        }

        return $date;
    }

    /**
     * Business days left after $today until $dueOn, counting $dueOn. Zero on
     * the last day itself; negative once it has passed.
     */
    public function leftUntil(CarbonImmutable $today, CarbonImmutable $dueOn): int
    {
        $today = $today->startOfDay();
        $dueOn = $dueOn->startOfDay();

        if ($dueOn->lessThan($today)) {
            return -$this->countBetween($dueOn, $today);
        }

        return $this->countBetween($today, $dueOn);
    }

    public function isBusinessDay(CarbonImmutable $date): bool
    {
        return ! $date->isWeekend()
            && ! in_array($date->format('m-d'), $this->holidays, true)
            && ! in_array($date->format('Y-m-d'), $this->holidays, true);
    }

    /**
     * Business days in ($from, $to].
     */
    private function countBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $count = 0;

        for ($date = $from->addDay(); $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $count += $this->isBusinessDay($date) ? 1 : 0;
        }

        return $count;
    }
}
