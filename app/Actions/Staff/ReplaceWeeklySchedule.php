<?php

namespace App\Actions\Staff;

use App\Actions\Booking\FindAffectedBookings;
use App\Domain\Schedule\TimeRange;
use App\Enums\ScheduleAccess;
use App\Exceptions\StaffException;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\StaffSchedule;
use Illuminate\Support\Facades\DB;

class ReplaceWeeklySchedule
{
    public function __construct(private readonly FindAffectedBookings $affected) {}

    /**
     * Replace the whole week of a professional in one go.
     *
     * @param  list<array{weekday: int, start: string, end: string, access: ScheduleAccess}>  $ranges  times as "HH:MM"
     */
    public function handle(Staff $staff, array $ranges): void
    {
        $this->ensureNoOverlaps($ranges);

        DB::transaction(function () use ($staff, $ranges): void {
            // Same lock the booking transaction takes: hours never change
            // halfway through someone booking this professional.
            Staff::query()->lockForUpdate()->findOrFail($staff->id);

            $timezone = Salon::withTrashed()->findOrFail($staff->salon_id)->timezone;
            $this->affected->ensureAllInside($staff, $ranges, $timezone);

            $staff->schedules()->delete();

            foreach ($ranges as $range) {
                $schedule = new StaffSchedule;
                $schedule->salon_id = $staff->salon_id;
                $schedule->staff_id = $staff->id;
                $schedule->weekday = $range['weekday'];
                $schedule->start_time = $range['start'];
                $schedule->end_time = $range['end'];
                $schedule->access_level = $range['access'];
                $schedule->save();
            }
        });
    }

    /**
     * @param  list<array{weekday: int, start: string, end: string, access: ScheduleAccess}>  $ranges
     */
    private function ensureNoOverlaps(array $ranges): void
    {
        $seen = [];

        foreach ($ranges as $range) {
            $candidate = TimeRange::fromStrings($range['start'], $range['end']);

            foreach ($seen[$range['weekday']] ?? [] as $other) {
                if ($candidate->overlaps($other)) {
                    throw StaffException::overlappingRanges();
                }
            }

            $seen[$range['weekday']][] = $candidate;
        }
    }
}
