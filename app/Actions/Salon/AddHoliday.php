<?php

namespace App\Actions\Salon;

use App\Exceptions\StaffException;
use App\Models\Salon;
use App\Models\SalonHoliday;
use Carbon\CarbonImmutable;

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
        $holiday = $salon->holidays()->whereDate('date', $date)->first();

        if ($holiday === null && $salon->holidays()->count() >= SalonHoliday::MAX_PER_SALON) {
            throw StaffException::holidayLimitReached(SalonHoliday::MAX_PER_SALON);
        }

        $holiday ??= new SalonHoliday;
        $holiday->salon_id = $salon->id;
        $holiday->date = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        $holiday->name = $name;
        $holiday->save();

        return $holiday;
    }
}
