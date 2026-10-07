<?php

namespace App\Actions\Salon;

use App\Exceptions\StaffException;
use App\Models\Salon;
use App\Models\SalonHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AddHoliday
{
    /**
     * Close the salon on a calendar day. Adding the same day again only
     * renames it.
     *
     * @param  string  $date  "Y-m-d" in the salon's timezone
     */
    public function handle(Salon $salon, string $date, string $name): SalonHoliday
    {
        return DB::transaction(function () use ($salon, $date, $name): SalonHoliday {
            // Serializes two saves of the same day and keeps the limit true.
            Salon::query()->lockForUpdate()->findOrFail($salon->id);

            $holiday = $salon->holidays()->whereDate('date', $date)->first();

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
