<?php

namespace App\Actions\Staff;

use App\Actions\Booking\FindAffectedBookings;
use App\Enums\TimeOffReason;
use App\Exceptions\StaffException;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AddTimeOff
{
    private const int MAX_UPCOMING = 60;

    public function __construct(private readonly FindAffectedBookings $affected) {}

    public function handle(
        Staff $staff,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        TimeOffReason $reason,
        ?string $note = null,
    ): StaffTimeOff {
        // Checked on real instants: a wall-clock time swallowed by a
        // daylight-saving jump can turn "00:30 to 01:15" upside down.
        if ($endsAtUtc->lessThanOrEqualTo($startsAtUtc)) {
            throw StaffException::invalidPeriod();
        }

        return DB::transaction(function () use ($staff, $startsAtUtc, $endsAtUtc, $reason, $note): StaffTimeOff {
            // Same lock as booking: nobody books this professional while
            // the absence is being created (and the limit stays true).
            Staff::query()->lockForUpdate()->findOrFail($staff->id);

            $this->affected->ensureNoneDuring($staff, $startsAtUtc, $endsAtUtc);

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
        });
    }
}
