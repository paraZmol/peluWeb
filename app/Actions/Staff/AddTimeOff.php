<?php

namespace App\Actions\Staff;

use App\Enums\TimeOffReason;
use App\Exceptions\StaffException;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use Carbon\CarbonImmutable;

class AddTimeOff
{
    private const int MAX_UPCOMING = 60;

    public function handle(
        Staff $staff,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        TimeOffReason $reason,
        ?string $note = null,
    ): StaffTimeOff {
        if ($staff->timeOff()->where('ends_at', '>', now())->count() >= self::MAX_UPCOMING) {
            throw StaffException::tooManyTimeOff(self::MAX_UPCOMING);
        }

        $timeOff = new StaffTimeOff;
        $timeOff->salon_id = $staff->salon_id;
        $timeOff->staff_id = $staff->id;
        $timeOff->starts_at = $startsAtUtc->utc();
        $timeOff->ends_at = $endsAtUtc->utc();
        $timeOff->reason = $reason;
        $timeOff->note = $note;
        $timeOff->save();

        return $timeOff;
    }
}
