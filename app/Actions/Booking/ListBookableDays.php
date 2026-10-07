<?php

namespace App\Actions\Booking;

use App\Enums\ClientLevel;
use App\Enums\ScheduleAccess;
use App\Models\Salon;
use App\Models\SalonHoliday;
use App\Models\StaffSchedule;

/**
 * The days a date picker shows, from today on the salon's clock. Names the
 * salon explicitly: visitors have no current salon.
 */
class ListBookableDays
{
    /**
     * Each day is flagged as closed when nobody could attend that kind of
     * client (no hours for their level that weekday, or a holiday).
     *
     * @return list<array{date: string, closed: bool}>
     */
    public function handle(Salon $salon, ClientLevel $level, int $daysAhead): array
    {
        $today = $salon->localNow()->startOfDay();
        $allowed = array_map(fn (ScheduleAccess $access) => $access->value, $level->allowedAccess());

        $workingWeekdays = StaffSchedule::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->whereIn('access_level', $allowed)
            ->whereHas('staff', fn ($staff) => $staff->withoutGlobalScope('salon')->where('is_active', true))
            ->distinct()
            ->pluck('weekday')
            ->all();

        $holidays = SalonHoliday::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->whereDate('date', '>=', $today->toDateString())
            ->get()
            ->map(fn (SalonHoliday $holiday): string => $holiday->date->format('Y-m-d'))
            ->all();

        $days = [];

        for ($offset = 0; $offset <= $daysAhead; $offset++) {
            $day = $today->addDays($offset);
            $days[] = [
                'date' => $day->toDateString(),
                'closed' => ! in_array($day->dayOfWeekIso, $workingWeekdays, true) || in_array($day->toDateString(), $holidays, true),
            ];
        }

        return $days;
    }
}
