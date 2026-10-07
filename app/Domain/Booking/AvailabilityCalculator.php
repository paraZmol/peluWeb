<?php

namespace App\Domain\Booking;

/**
 * The single place that decides whether a professional can take a visit
 * at a given instant. Pure: no database and no clock of its own, so the
 * client's time picker and the booking transaction always agree.
 *
 * Rules:
 * - every busy stretch of the visit lies entirely inside one working window
 *   (windows already exclude hours the client may not book: VIP, team only,
 *   holidays);
 * - no busy stretch overlaps a busy span (other bookings, time off). Spans
 *   that only touch do not overlap;
 * - the idle (processing) time of the visit needs neither: the professional
 *   may attend someone else, or be on their lunch break, meanwhile.
 */
final class AvailabilityCalculator
{
    private const int SECONDS_PER_MINUTE = 60;

    /**
     * Start instants a client can pick, sorted.
     *
     * @param  list<TimeSpan>  $windows  when the professional works, for this client
     * @param  list<TimeSpan>  $busy  what is already taken
     * @param  int  $stepMinutes  grid of offered starts, counted from each window's opening
     * @param  int  $earliest  first acceptable start (now + the salon's minimum notice)
     * @param  int  $latest  last acceptable start (the salon's booking horizon)
     * @return list<int>
     */
    public function slots(array $windows, array $busy, BookingShape $shape, int $stepMinutes, int $earliest, int $latest): array
    {
        $step = max(1, $stepMinutes) * self::SECONDS_PER_MINUTE;
        $firstBusy = $shape->firstBusyMinutes() * self::SECONDS_PER_MINUTE;
        $slots = [];

        if ($firstBusy === 0) {
            return [];
        }

        foreach ($this->candidates($windows, $busy, $step, $firstBusy) as $start) {
            if ($start >= $earliest && $start <= $latest && $this->fits($start, $windows, $busy, $shape)) {
                $slots[$start] = $start;
            }
        }

        sort($slots);

        return $slots;
    }

    /**
     * Starts worth checking: the grid of each window, plus the instant each
     * busy span ends. Without the latter a gap that does not begin on the
     * grid (the 35 minutes a color is processing, from 10:20) would be
     * wasted even when the service fits in it.
     *
     * @param  list<TimeSpan>  $windows
     * @param  list<TimeSpan>  $busy
     * @return list<int>
     */
    private function candidates(array $windows, array $busy, int $step, int $firstBusy): array
    {
        $candidates = array_map(fn (TimeSpan $taken): int => $taken->end, $busy);

        foreach ($windows as $window) {
            for ($start = $window->start; $start + $firstBusy <= $window->end; $start += $step) {
                $candidates[] = $start;
            }
        }

        return $candidates;
    }

    /**
     * Whether a visit of this shape can start exactly at $start.
     *
     * @param  list<TimeSpan>  $windows
     * @param  list<TimeSpan>  $busy
     */
    public function fits(int $start, array $windows, array $busy, BookingShape $shape): bool
    {
        $stretches = $shape->busyAt($start);

        if ($stretches === []) {
            return false;
        }

        foreach ($stretches as $stretch) {
            if (! $this->insideOneWindow($stretch, $windows) || $this->collides($stretch, $busy)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<TimeSpan>  $windows
     */
    private function insideOneWindow(TimeSpan $stretch, array $windows): bool
    {
        foreach ($windows as $window) {
            if ($window->covers($stretch)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<TimeSpan>  $busy
     */
    private function collides(TimeSpan $stretch, array $busy): bool
    {
        foreach ($busy as $taken) {
            if ($stretch->overlaps($taken)) {
                return true;
            }
        }

        return false;
    }
}
