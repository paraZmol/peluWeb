<?php

namespace App\Actions\Booking;

use App\Domain\Accounting\Checkout;
use App\Domain\Accounting\CommissionCalculator;
use App\Domain\Booking\AvailabilityCalculator;
use App\Domain\Booking\TimeSpan;
use App\Enums\BookingNotice;
use App\Enums\BookingStatus;
use App\Enums\ClientLevel;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\Payment;
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
    private const int DEADLOCK_ATTEMPTS = 3;

    public function __construct(
        private readonly StaffAvailability $availability,
        private readonly AvailabilityCalculator $calculator,
        private readonly UpdateReferencePhoto $photos,
        private readonly NotifyClient $notify,
    ) {}

    /**
     * The team confirms: the deposit arrived, or they waive it.
     */
    public function confirm(Booking $booking, ?User $by, PaymentMethod $depositMethod = PaymentMethod::Other): Booking
    {
        $confirmed = $this->change($booking, BookingStatus::Confirmed, $by, function (Booking $locked, CarbonImmutable $now) use ($by, $depositMethod): void {
            // An expired hold released the slot: it can only be confirmed
            // if nobody took that time in the meantime.
            if ($locked->isHoldExpired($now) && ! $this->slotStillFree($locked, $now)) {
                throw BookingException::slotTaken();
            }

            if ($locked->deposit_cents > 0) {
                $locked->deposit_paid_at = $now;
                Payment::record($locked, PaymentKind::Deposit, $depositMethod, $locked->deposit_cents, $now, $by);
            }

            $locked->hold_expires_at = null;
        });
        $this->notify->handle($confirmed, BookingNotice::Confirmed);

        return $confirmed;
    }

    public function start(Booking $booking, ?User $by): Booking
    {
        return $this->change($booking, BookingStatus::InService, $by, function (Booking $locked, CarbonImmutable $now): void {
            $timezone = Salon::withTrashed()->findOrFail($locked->salon_id)->timezone;

            // A slip of the finger on next week's visit would leave it "in
            // service", from where it can only be completed.
            if (! $locked->starts_at->setTimezone($timezone)->isSameDay($now->setTimezone($timezone))) {
                throw BookingException::notToday();
            }
        });
    }

    /**
     * The visit ends and is paid for. Without a checkout it is charged in
     * full, with no discount.
     */
    public function complete(Booking $booking, ?User $by, ?Checkout $checkout = null): Booking
    {
        return $this->change($booking, BookingStatus::Completed, $by, function (Booking $locked, CarbonImmutable $now) use ($by, $checkout): void {
            $depositPaid = $locked->depositPaidCents();
            $checkout ??= Checkout::inFull(PaymentMethod::Other, $locked->price_cents, $depositPaid);
            $checkout->assertSettles($locked->price_cents, $depositPaid);

            $locked->discount_cents = $checkout->discountCents;
            $locked->charged_cents = $checkout->chargedCents($locked->price_cents);
            $locked->commission_cents = $this->commissionFor($locked);

            foreach ($checkout->receivedParts() as $method => $cents) {
                Payment::record($locked, PaymentKind::Charge, PaymentMethod::from($method), $cents, $now, $by);
            }
        });
    }

    /**
     * @param  bool  $byClient  clients cannot cancel once the visit started, and lose a paid deposit inside the salon's cancellation window
     */
    public function cancel(Booking $booking, ?User $by, bool $byClient, ?string $note = null): Booking
    {
        $cancelled = $this->change($booking, BookingStatus::Cancelled, $by, function (Booking $locked, CarbonImmutable $now) use ($byClient): void {
            if ($byClient && $locked->starts_at->lessThanOrEqualTo($now)) {
                throw BookingException::alreadyStarted();
            }

            if ($locked->deposit_paid_at !== null) {
                $window = Salon::withTrashed()->findOrFail($locked->salon_id)->cancel_window_hours;
                $locked->deposit_refundable = ! $byClient || $now->addHours($window)->lessThanOrEqualTo($locked->starts_at);
            }

            $locked->hold_expires_at = null;
        }, $note);
        $this->notify->handle($cancelled, BookingNotice::Cancelled);

        return $cancelled;
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
        $changed = DB::transaction(function () use ($booking, $to, $by, $apply, $note): Booking {
            Staff::withoutGlobalScope('salon')->withTrashed()->lockForUpdate()->find($booking->staff_id);
            $locked = Booking::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($booking->id);
            $now = CarbonImmutable::now();
            $from = $locked->status;

            // Moved to another professional while this waited: the row
            // locked above is no longer the one that guards its slot.
            if ($locked->staff_id !== $booking->staff_id) {
                throw BookingException::changedMeanwhile();
            }

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
        }, self::DEADLOCK_ATTEMPTS);

        $this->forgetReferencePhoto($changed);

        return $changed;
    }

    /**
     * What the professional earns for this visit, with today's rules: their
     * general percent, or what each service says for them. Frozen on the
     * booking, so changing a rule later never rewrites past visits.
     */
    private function commissionFor(Booking $booking): int
    {
        $staff = Staff::withoutGlobalScope('salon')->withTrashed()->findOrFail($booking->staff_id);
        $rules = DB::table('service_staff')->where('staff_id', $staff->id)->get(['service_id', 'commission_percent', 'commission_cents'])->keyBy('service_id');
        $lines = [];

        foreach ($booking->services()->get() as $service) {
            $rule = $rules->get($service->service_id);

            $lines[] = [
                'priceCents' => $service->price_cents,
                'percent' => (int) ($rule->commission_percent ?? $staff->commission_percent),
                'fixedCents' => $rule?->commission_cents === null ? null : (int) $rule->commission_cents,
            ];
        }

        return CommissionCalculator::forVisit($lines, $booking->discount_cents);
    }

    /**
     * Once a visit is over or called off nobody needs the client's
     * reference picture, and it should not stay on a public address.
     */
    private function forgetReferencePhoto(Booking $booking): void
    {
        if ($booking->reference_photo_path !== null && $booking->status->isFinal()) {
            $this->photos->remove($booking);
        }
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

        return $this->calculator->fits(
            $booking->starts_at->getTimestamp(),
            $this->availability->windows($salon, $staff, $localDate, ClientLevel::Team),
            $this->availability->busy($staff, $day, $now, $booking->id),
            $booking->shape(),
        );
    }
}
