<?php

namespace App\Actions\Salon;

use App\Actions\Booking\FindAffectedBookings;
use App\Exceptions\StaffException;
use App\Models\Salon;
use App\Models\SalonHoliday;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AddHoliday
{
    public function __construct(private readonly FindAffectedBookings $affected) {}

    /**
     * Close the salon on a calendar day. Adding the same day again only
     * renames it.
     *
     * @param  string  $date  "Y-m-d" in the salon's timezone
     */
    public function handle(Salon $salon, string $date, string $name): SalonHoliday
    {
        return DB::transaction(function () use ($salon, $date, $name): SalonHoliday {
            // Every professional first, the same lock a booking or a move
            // takes: nothing lands on the day between the check below and
            // the holiday being stored. Then the salon, which serializes
            // two saves of the same day and keeps the limit true.
            Staff::withoutGlobalScope('salon')->where('salon_id', $salon->id)->orderBy('id')->lockForUpdate()->pluck('id');
            Salon::query()->lockForUpdate()->findOrFail($salon->id);

            $holiday = $salon->holidays()->whereDate('date', $date)->first();

            if ($holiday === null) {
                $this->affected->ensureNoneOn($salon, $date);
            }

            if ($holiday === null && $this->upcomingCount($salon) >= SalonHoliday::MAX_PER_SALON) {
                throw StaffException::holidayLimitReached(SalonHoliday::MAX_PER_SALON);
            }

            $holiday ??= new SalonHoliday;
            $holiday->salon_id = $salon->id;
            $holiday->date = CarbonImmutable::createFromFormat('!Y-m-d', $date);
            $holiday->name = $name;
            $holiday->save();

            return $holiday;
        });
    }

    /**
     * Past holidays do not count: the screen only lists upcoming ones.
     */
    private function upcomingCount(Salon $salon): int
    {
        return $salon->holidays()->whereDate('date', '>=', $salon->localNow()->toDateString())->count();
    }
}
