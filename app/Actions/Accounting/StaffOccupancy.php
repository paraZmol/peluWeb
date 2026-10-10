<?php

namespace App\Actions\Accounting;

use App\Domain\Accounting\OpenTime;
use App\Domain\Booking\TimeSpan;
use App\Domain\Booking\WorkingWindows;
use App\Enums\ScheduleAccess;
use App\Models\BookingSegment;
use App\Models\SalonHoliday;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;

/**
 * Hours each professional spent attending against the hours they were
 * there for it, in minutes.
 *
 * "There" is the weekly schedule as it is today (its past versions are not
 * kept) minus salon holidays and time off; "attending" is the busy
 * stretches of completed visits, so the resting time of a tint does not
 * count. Someone attended outside their hours can go over 100 %.
 */
class StaffOccupancy
{
    private const int SECONDS_PER_MINUTE = 60;

    /**
     * Only who had hours or attended somebody, busiest first.
     *
     * @return array<int, array{name: string, openMinutes: int, busyMinutes: int}>
     */
    public function handle(ReportScope $scope): array
    {
        $busy = $this->busyMinutes($scope);
        $open = $this->openMinutes($scope);

        return Staff::withoutGlobalScope('salon')->withTrashed()
            ->where('salon_id', $scope->salon->id)
            ->whereIn('id', [...array_keys($busy), ...array_keys($open)])
            ->orderBy('position')->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Staff $staff): array => [
                'name' => $staff->name,
                'openMinutes' => $open[$staff->id] ?? 0,
                'busyMinutes' => $busy[$staff->id] ?? 0,
            ])
            ->sortByDesc('busyMinutes')
            ->values()->all();
    }

    /**
     * @return array<int, int> minutes by professional
     */
    private function busyMinutes(ReportScope $scope): array
    {
        return BookingSegment::query()
            ->whereIn('booking_id', $scope->completed()->select('bookings.id'))
            ->selectRaw('staff_id, SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at)) as minutes')
            ->groupBy('staff_id')->toBase()->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->staff_id => (int) $row->minutes])
            ->all();
    }

    /**
     * @return array<int, int> minutes by professional; nobody without hours
     */
    private function openMinutes(ReportScope $scope): array
    {
        $period = $scope->period;
        $holidays = SalonHoliday::withoutGlobalScope('salon')
            ->where('salon_id', $scope->salon->id)
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->get()
            ->mapWithKeys(fn (SalonHoliday $holiday): array => [$holiday->date->format('Y-m-d') => true]);
        $timeOff = $this->timeOff($scope);
        $minutes = [];

        foreach ($this->schedules($scope) as $staffId => $ranges) {
            $seconds = 0;

            for ($day = $period->from; $day->lessThanOrEqualTo($period->to); $day = $day->addDay()) {
                if ($holidays->has($day->format('Y-m-d'))) {
                    continue;
                }

                $windows = WorkingWindows::forDate($day, $ranges, ScheduleAccess::cases());
                $seconds += OpenTime::seconds($windows, $timeOff[$staffId] ?? []);
            }

            $minutes[$staffId] = intdiv($seconds, self::SECONDS_PER_MINUTE);
        }

        return array_filter($minutes);
    }

    /**
     * Weekly hours of who attends today: not of who left or was switched off.
     *
     * @return array<int, list<array{weekday: int, start: string, end: string, access: ScheduleAccess}>>
     */
    private function schedules(ReportScope $scope): array
    {
        $attending = Staff::withoutGlobalScope('salon')
            ->where('salon_id', $scope->salon->id)
            ->where('is_active', true)
            ->select('id');
        $ranges = [];

        $rows = StaffSchedule::withoutGlobalScope('salon')
            ->where('salon_id', $scope->salon->id)
            ->whereIn('staff_id', $attending)
            ->get();

        foreach ($rows as $schedule) {
            $ranges[$schedule->staff_id][] = [
                'weekday' => $schedule->weekday,
                'start' => $schedule->start(),
                'end' => $schedule->end(),
                'access' => $schedule->access_level,
            ];
        }

        return $ranges;
    }

    /**
     * @return array<int, list<TimeSpan>> time off touching the period, by professional
     */
    private function timeOff(ReportScope $scope): array
    {
        $spans = [];

        $rows = StaffTimeOff::withoutGlobalScope('salon')
            ->where('salon_id', $scope->salon->id)
            ->where('starts_at', '<', $scope->period->endsBeforeUtc())
            ->where('ends_at', '>', $scope->period->startsAtUtc())
            ->whereColumn('starts_at', '<', 'ends_at')
            ->get(['id', 'staff_id', 'starts_at', 'ends_at']);

        foreach ($rows as $off) {
            $spans[$off->staff_id][] = new TimeSpan($off->starts_at->getTimestamp(), $off->ends_at->getTimestamp());
        }

        return $spans;
    }
}
