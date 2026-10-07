<?php

namespace App\Actions\Booking;

use App\Domain\Booking\TimeSpan;
use App\Domain\Booking\WorkingWindows;
use App\Enums\ScheduleAccess;
use App\Exceptions\StaffException;
use App\Models\Booking;
use App\Models\BookingSegment;
use App\Models\Salon;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Finds the upcoming appointments a change would leave stranded (new
 * working hours, time off, a holiday, removing a professional), so the
 * change is refused with the list instead of breaking them in silence.
 *
 * Call it with the professional's row already locked: the same lock a
 * booking takes, so nothing is booked between the check and the change.
 * Queries name the professional or the salon explicitly.
 */
class FindAffectedBookings
{
    /** How many appointments the message names before "and N more". */
    private const int LISTED = 3;

    /**
     * Refuse if this professional has appointments inside the period.
     */
    public function ensureNoneDuring(Staff $staff, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): void
    {
        $this->refuse($this->upcoming(fn (Builder $query) => $query->where('staff_id', $staff->id))
            ->filter(fn (Booking $booking) => $booking->segments->contains(
                fn (BookingSegment $segment) => $segment->starts_at->lessThan($toUtc) && $segment->ends_at->greaterThan($fromUtc),
            )), $staff->salon_id);
    }

    /**
     * Refuse if anyone in the salon has appointments on that calendar day.
     *
     * @param  string  $date  "Y-m-d" on the salon's clock
     */
    public function ensureNoneOn(Salon $salon, string $date): void
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $salon->timezone);

        if (! $day instanceof CarbonImmutable) {
            return;
        }

        $this->refuse($this->upcoming(fn (Builder $query) => $query
            ->where('salon_id', $salon->id)
            ->where('starts_at', '>=', $day->utc())
            ->where('starts_at', '<', $day->addDay()->utc())), $salon->id);
    }

    /**
     * Refuse if this professional has any upcoming appointment at all.
     */
    public function ensureNone(Staff $staff): void
    {
        $this->refuse($this->upcoming(fn (Builder $query) => $query->where('staff_id', $staff->id)), $staff->salon_id);
    }

    /**
     * Refuse if new weekly hours would leave an appointment (any of its
     * working stretches) outside them.
     *
     * @param  list<array{weekday: int, start: string, end: string, access: ScheduleAccess}>  $ranges
     */
    public function ensureAllInside(Staff $staff, array $ranges, string $timezone): void
    {
        $this->refuse($this->upcoming(fn (Builder $query) => $query->where('staff_id', $staff->id))
            ->reject(function (Booking $booking) use ($ranges, $timezone): bool {
                // Any access level: the team may have booked it in reserved hours.
                $windows = WorkingWindows::forDate($booking->starts_at->setTimezone($timezone), $ranges, ScheduleAccess::cases());

                return $booking->segments->every(fn (BookingSegment $segment) => collect($windows)->contains(
                    fn (TimeSpan $window) => $window->covers(new TimeSpan($segment->starts_at->getTimestamp(), $segment->ends_at->getTimestamp())),
                ));
            }), $staff->salon_id);
    }

    /**
     * Appointments that still hold their slot and have not finished.
     *
     * @param  callable(Builder<Booking>): mixed  $scope
     * @return Collection<int, Booking>
     */
    private function upcoming(callable $scope): Collection
    {
        $unscoped = fn ($query) => $query->withoutGlobalScope('salon');

        return Booking::withoutGlobalScope('salon')
            ->tap($scope)
            ->where('ends_at', '>', now())
            ->holdingSlot()
            ->with(['segments', 'client' => $unscoped])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    private function refuse(Collection $bookings, int $salonId): void
    {
        if ($bookings->isEmpty()) {
            return;
        }

        $timezone = Salon::withTrashed()->findOrFail($salonId)->timezone;

        $listed = $bookings->take(self::LISTED)->map(fn (Booking $booking): string => sprintf(
            '%s (%s)',
            $booking->starts_at->setTimezone($timezone)->format('d/m g:i a'),
            $booking->client->name,
        ))->join(', ');

        throw StaffException::bookingsAffected($bookings->count(), $listed);
    }
}
