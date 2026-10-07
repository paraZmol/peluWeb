<?php

namespace App\Actions\Booking;

use App\Domain\Booking\AvailabilityCalculator;
use App\Domain\Booking\BookingShape;
use App\Domain\Booking\TimeSpan;
use App\Domain\Booking\WorkingWindows;
use App\Enums\ClientLevel;
use App\Models\Booking;
use App\Models\BookingSegment;
use App\Models\Salon;
use App\Models\SalonHoliday;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\StaffTimeOff;
use Carbon\CarbonImmutable;

/**
 * Feeds the pure AvailabilityCalculator with one professional's day from
 * the database. Used both to show the time picker and, inside the booking
 * transaction, to check again: one code path, so they cannot disagree.
 *
 * Names the salon explicitly: visitors have no current salon.
 */
class StaffAvailability
{
    private const int TEAM_HORIZON_DAYS = 365;

    public function __construct(private readonly AvailabilityCalculator $calculator) {}

    /**
     * Start instants (Unix, UTC) this professional can take on that day.
     *
     * @param  CarbonImmutable  $localDate  any instant of the day, in the salon's timezone
     * @param  int|null  $ignoreBookingId  a booking being moved does not block itself
     * @return list<int>
     */
    public function slots(
        Salon $salon,
        Staff $staff,
        BookingShape $shape,
        CarbonImmutable $localDate,
        ClientLevel $level,
        CarbonImmutable $now,
        ?int $ignoreBookingId = null,
    ): array {
        $windows = $this->windows($salon, $staff, $localDate, $level);

        if ($windows === []) {
            return [];
        }

        $day = new TimeSpan($localDate->startOfDay()->getTimestamp(), $localDate->addDay()->startOfDay()->getTimestamp());
        [$earliest, $latest] = $this->bounds($salon, $level, $now);

        return $this->calculator->slots(
            $windows,
            $this->busy($staff, $day, $now, $ignoreBookingId),
            $shape,
            $salon->slot_step_minutes,
            $earliest,
            $latest,
        );
    }

    /**
     * When the professional works that day for this kind of client. A
     * salon holiday closes the day for everyone, the team included.
     *
     * @return list<TimeSpan>
     */
    public function windows(Salon $salon, Staff $staff, CarbonImmutable $localDate, ClientLevel $level): array
    {
        $isHoliday = SalonHoliday::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->whereDate('date', $localDate->toDateString())
            ->exists();

        if ($isHoliday) {
            return [];
        }

        $ranges = StaffSchedule::withoutGlobalScope('salon')
            ->where('staff_id', $staff->id)
            ->where('weekday', $localDate->dayOfWeekIso)
            ->get()
            ->map(fn (StaffSchedule $schedule): array => [
                'weekday' => $schedule->weekday,
                'start' => $schedule->start(),
                'end' => $schedule->end(),
                'access' => $schedule->access_level,
            ]);

        return WorkingWindows::forDate($localDate, $ranges, $level->allowedAccess());
    }

    /**
     * What already takes the professional's time inside $range: the busy
     * stretches of bookings that hold their slot, and time off.
     *
     * @return list<TimeSpan>
     */
    public function busy(Staff $staff, TimeSpan $range, CarbonImmutable $now, ?int $ignoreBookingId = null): array
    {
        $from = CarbonImmutable::createFromTimestampUTC($range->start);
        $to = CarbonImmutable::createFromTimestampUTC($range->end);

        $segments = BookingSegment::query()
            ->where('staff_id', $staff->id)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->when($ignoreBookingId !== null, fn ($query) => $query->where('booking_id', '!=', $ignoreBookingId))
            ->whereIn('booking_id', Booking::withoutGlobalScope('salon')->where('staff_id', $staff->id)->holdingSlot($now)->select('id'))
            ->get()
            ->map(fn (BookingSegment $segment) => new TimeSpan($segment->starts_at->getTimestamp(), $segment->ends_at->getTimestamp()));

        $timeOff = StaffTimeOff::withoutGlobalScope('salon')
            ->where('staff_id', $staff->id)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get()
            ->map(fn (StaffTimeOff $off) => new TimeSpan($off->starts_at->getTimestamp(), $off->ends_at->getTimestamp()));

        return [...$segments->all(), ...$timeOff->all()];
    }

    /**
     * First and last start the salon accepts. Clients need the minimum
     * notice and stay inside the booking horizon; the team may book for
     * right now (walk-ins) and far ahead.
     *
     * @return array{int, int}
     */
    private function bounds(Salon $salon, ClientLevel $level, CarbonImmutable $now): array
    {
        if ($level === ClientLevel::Team) {
            return [
                $now->subMinutes($salon->slot_step_minutes)->getTimestamp(),
                $now->addDays(self::TEAM_HORIZON_DAYS)->getTimestamp(),
            ];
        }

        return [
            $now->addMinutes($salon->min_lead_minutes)->getTimestamp(),
            $now->setTimezone($salon->timezone)->addDays($salon->max_days_ahead)->endOfDay()->getTimestamp(),
        ];
    }
}
