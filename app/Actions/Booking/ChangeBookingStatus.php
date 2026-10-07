<?php

namespace App\Actions\Booking;

use App\Domain\Booking\AvailabilityCalculator;
use App\Domain\Booking\BookingShape;
use App\Domain\Booking\TimeSpan;
use App\Enums\BookingStatus;
use App\Enums\ClientLevel;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Every status change of a booking goes through here: it enforces the
 * state machine (BookingStatus::next) and writes the audit trail.
 *
 * Each change locks the professional first and the booking second — the
 * same order CreateBooking uses — so a slot freed or re-taken here never
 * races with someone booking it.
 */
class ChangeBookingStatus
{
    public function __construct(
        private readonly StaffAvailability $availability,
        private readonly AvailabilityCalculator $calculator,
    ) {}

    /**
     * The team confirms: the deposit arrived, or they waive it.
     */
    public function confirm(Booking $booking, ?User $by): Booking
    {
        return $this->change($booking, BookingStatus::Confirmed, $by, function (Booking $locked, CarbonImmutable $now): void {
            // An expired hold released the slot: it can only be confirmed
            // if nobody took that time in the meantime.
            if ($locked->isHoldExpired($now) && ! $this->slotStillFree($locked, $now)) {
                throw BookingException::slotTaken();
            }

            if ($locked->deposit_cents > 0) {
                $locked->deposit_paid_at = $now;
            }

            $locked->hold_expires_at = null;
        });
    }

    public function start(Booking $booking, ?User $by): Booking
    {
        return $this->change($booking, BookingStatus::InService, $by);
    }

    public function complete(Booking $booking, ?User $by): Booking
    {
        return $this->change($booking, BookingStatus::Completed, $by);
    }

    /**
     * @param  bool  $byClient  clients cannot cancel once the visit started, and lose a paid deposit inside the salon's cancellation window
     */
    public function cancel(Booking $booking, ?User $by, bool $byClient, ?string $note = null): Booking
    {
        return $this->change($booking, BookingStatus::Cancelled, $by, function (Booking $locked, CarbonImmutable $now) use ($byClient): void {
            if ($byClient && $locked->starts_at->lessThanOrEqualTo($now)) {
                throw BookingException::alreadyStarted();
            }

            if ($locked->deposit_paid_at !== null) {
                $window = Salon::withTrashed()->findOrFail($locked->salon_id)->cancel_window_hours;
                $locked->deposit_refundable = ! $byClient || $now->addHours($window)->lessThanOrEqualTo($locked->starts_at);
            }

            $locked->hold_expires_at = null;
        }, $note);
    }

    public function markNoShow(Booking $booking, ?User $by): Booking
    {
        return $this->change($booking, BookingStatus::NoShow, $by, function (Booking $locked, CarbonImmutable $now): void {
            if ($locked->starts_at->greaterThan($now)) {
                throw BookingException::notStartedYet();
            }

            $locked->client()->withoutGlobalScope('salon')->increment('no_show_count');

            if ($locked->deposit_paid_at !== null) {
                $locked->deposit_refundable = false;
            }
        });
    }

    /**
     * Record that a hold ran out. The slot was already free: this only
     * tidies the stored status. Does nothing if the hold is still alive.
     */
    public function expire(Booking $booking): ?Booking
    {
        if (! $booking->isHoldExpired()) {
            return null;
        }

        try {
            return $this->change($booking, BookingStatus::Expired, null, function (Booking $locked, CarbonImmutable $now): void {
                if (! $locked->isHoldExpired($now)) {
                    throw BookingException::cannotChange($locked->status->label());
                }
            });
        } catch (BookingException) {
            // Confirmed or cancelled a moment ago: nothing left to expire.
            return null;
        }
    }

    /**
     * @param  (Closure(Booking, CarbonImmutable): void)|null  $apply  extra checks and field changes, run under the locks
     */
    private function change(Booking $booking, BookingStatus $to, ?User $by, ?Closure $apply = null, ?string $note = null): Booking
    {
        return DB::transaction(function () use ($booking, $to, $by, $apply, $note): Booking {
            Staff::withoutGlobalScope('salon')->withTrashed()->lockForUpdate()->find($booking->staff_id);
            $locked = Booking::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($booking->id);
            $now = CarbonImmutable::now();
            $from = $locked->status;

            if (! $from->canBecome($to)) {
                throw BookingException::cannotChange($locked->effectiveStatus($now)->label());
            }

            if ($apply !== null) {
                $apply($locked, $now);
            }

            $locked->status = $to;
            $locked->save();

            $event = new BookingEvent;
            $event->booking_id = $locked->id;
            $event->from_status = $from;
            $event->to_status = $to;
            $event->changed_by = $by?->id;
            $event->note = $note;
            $event->save();

            return $locked;
        });
    }

    /**
     * Whether the booking's own stretches are still free in its
     * professional's agenda (ignoring itself), in any working hours.
     */
    private function slotStillFree(Booking $booking, CarbonImmutable $now): bool
    {
        $salon = Salon::withTrashed()->findOrFail($booking->salon_id);
        $staff = Staff::withoutGlobalScope('salon')->withTrashed()->findOrFail($booking->staff_id);
        $localDate = $booking->starts_at->setTimezone($salon->timezone);
        $day = new TimeSpan($localDate->startOfDay()->getTimestamp(), $localDate->addDay()->startOfDay()->getTimestamp());

        $durations = [];

        foreach ($booking->services()->get() as $service) {
            $durations[] = [
                'active' => $service->active_minutes,
                'idle' => $service->idle_minutes,
                'finish' => $service->finish_minutes,
            ];
        }

        $shape = BookingShape::fromServices($durations);

        return $this->calculator->fits(
            $booking->starts_at->getTimestamp(),
            $this->availability->windows($salon, $staff, $localDate, ClientLevel::Team),
            $this->availability->busy($staff, $day, $now, $booking->id),
            $shape,
        );
    }
}
