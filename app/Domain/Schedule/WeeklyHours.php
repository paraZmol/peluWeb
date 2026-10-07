<?php

namespace App\Domain\Schedule;

use Carbon\CarbonImmutable;

/**
 * The opening hours of a salon: the union of the working ranges of its
 * professionals, per ISO weekday (1 = Monday … 7 = Sunday). Pure: no
 * database and no clock of its own.
 */
final readonly class WeeklyHours
{
    /** How far ahead to look for the next opening (covers long holidays). */
    private const int LOOKAHEAD_DAYS = 14;

    /**
     * @param  array<int, list<TimeRange>>  $days  merged, sorted ranges keyed by weekday
     */
    private function __construct(private array $days) {}

    /**
     * @param  iterable<array{weekday: int, range: TimeRange}>  $entries
     */
    public static function fromRanges(iterable $entries): self
    {
        $byDay = array_fill(1, 7, []);

        foreach ($entries as $entry) {
            $byDay[$entry['weekday']][] = $entry['range'];
        }

        return new self(array_map(self::merge(...), $byDay));
    }

    /**
     * @return array<int, list<TimeRange>>
     */
    public function days(): array
    {
        return $this->days;
    }

    public function isEmpty(): bool
    {
        return array_filter($this->days) === [];
    }

    /**
     * @param  CarbonImmutable  $localNow  current time in the salon's timezone
     * @param  array<int, string>  $closedDates  holidays as "Y-m-d" in the salon's timezone
     */
    public function statusAt(CarbonImmutable $localNow, array $closedDates = []): OpeningStatus
    {
        $minute = $localNow->hour * 60 + $localNow->minute;

        foreach ($this->rangesOn($localNow, $closedDates) as $range) {
            if ($range->contains($minute)) {
                return new OpeningStatus(isOpen: true, time: $range->end());
            }

            if ($minute < $range->startMinute) {
                return new OpeningStatus(false, $range->start(), 0, $localNow->dayOfWeekIso);
            }
        }

        for ($ahead = 1; $ahead <= self::LOOKAHEAD_DAYS; $ahead++) {
            $day = $localNow->addDays($ahead);
            $first = $this->rangesOn($day, $closedDates)[0] ?? null;

            if ($first !== null) {
                return new OpeningStatus(false, $first->start(), $ahead, $day->dayOfWeekIso);
            }
        }

        return new OpeningStatus(isOpen: false);
    }

    /**
     * @param  array<int, string>  $closedDates
     * @return list<TimeRange>
     */
    private function rangesOn(CarbonImmutable $day, array $closedDates): array
    {
        return in_array($day->format('Y-m-d'), $closedDates, true) ? [] : $this->days[$day->dayOfWeekIso];
    }

    /**
     * Sort and join ranges that overlap or touch (9–13 + 13–18 = 9–18).
     *
     * @param  list<TimeRange>  $ranges
     * @return list<TimeRange>
     */
    private static function merge(array $ranges): array
    {
        usort($ranges, fn (TimeRange $a, TimeRange $b) => $a->startMinute <=> $b->startMinute);
        $merged = [];
        $open = null;

        foreach ($ranges as $range) {
            if ($open !== null && $range->startMinute <= $open->endMinute) {
                $open = new TimeRange($open->startMinute, max($open->endMinute, $range->endMinute));

                continue;
            }

            if ($open !== null) {
                $merged[] = $open;
            }

            $open = $range;
        }

        if ($open !== null) {
            $merged[] = $open;
        }

        return $merged;
    }
}
