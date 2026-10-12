<?php

namespace App\Actions\Booking;

use App\Data\Booking\BookingMove;
use App\Enums\BookingNotice;
use App\Enums\BookingStatus;
use App\Enums\ClientLevel;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingService;
use App\Models\Promotion;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Moves a booking to another time, and (only the team) to another
 * professional. It is checked exactly like a new booking: the professionals
 * involved are locked first, and the new start must be one the time picker
 * would offer, ignoring the booking's own current place.
 *
 * As with CreateBooking, never call it inside a transaction that has
 * already read anything.
 */
class RescheduleBooking
{
    /** Times a client may move one booking by themselves. */
    public const int MAX_BY_CLIENT = 3;

    private const int DEADLOCK_ATTEMPTS = 3;

    public function __construct(
        private readonly StaffAvailability $availability,
        private readonly SyncBookingSegments $segments,
        private readonly NotifyClient $notify,
    ) {}

    public function handle(BookingMove $move): Booking
    {
        $moved = DB::transaction(fn (): Booking => $this->move($move), self::DEADLOCK_ATTEMPTS);
        $this->notify->handle($moved, BookingNotice::Moved);

        return $moved;
    }

    /**
     * Starts (Unix, UTC) the booking could move to on that day. Empty when
     * it cannot be moved at all.
     *
     * @param  CarbonImmutable  $localDate  any instant of the day, in the salon's timezone
     * @return list<int>
     */
    public function slots(Booking $booking, CarbonImmutable $localDate, bool $byClient, ?Staff $staff = null): array
    {
        $now = CarbonImmutable::now();
        $salon = Salon::query()->findOrFail($booking->salon_id);
        $target = $staff ?? Staff::withoutGlobalScope('salon')->find($booking->staff_id);

        if ($target === null || ! $this->canTake($target, $booking) || ! $this->isMovable($booking, $salon, $byClient, $now)) {
            return [];
        }

        $slots = $this->availability->slots(
            $salon, $target, $booking->shape(), $localDate, $this->level($booking, $byClient), $now, $booking->id,
        );

        // A client keeps the promotion's price only where it still applies.
        return $byClient
            ? array_values(array_filter($slots, fn (int $start): bool => $this->keepsPromotion($booking, $start, $salon)))
            : $slots;
    }

    /**
     * Whether a visit starting then is still one the booking's promotion
     * covers. The salon may move it anywhere and the price stays; a client
     * may not, or a "quiet hours" price would follow them to a Saturday.
     */
    private function keepsPromotion(Booking $booking, int $start, Salon $salon): bool
    {
        if ($booking->promotion_id === null) {
            return true;
        }

        $promotion = Promotion::withoutGlobalScope('salon')->withTrashed()->with('services')->find($booking->promotion_id);

        return $promotion === null
            || $promotion->terms()->covers(CarbonImmutable::createFromTimestampUTC($start)->setTimezone($salon->timezone));
    }

    private function move(BookingMove $move): Booking
    {
        $targetId = $move->staff->id ?? $move->booking->staff_id;

        // Both professionals at once and in id order, so two moves that
        // cross each other cannot deadlock.
        $staff = Staff::withoutGlobalScope('salon')
            ->whereIn('id', [$move->booking->staff_id, $targetId])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $booking = Booking::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($move->booking->id);
        $now = CarbonImmutable::now();
        $salon = Salon::query()->findOrFail($booking->salon_id);
        $target = $staff->get($targetId);

        // Someone moved it to another professional while this waited: the
        // rows locked above are no longer the right ones.
        if ($booking->staff_id !== $move->booking->staff_id) {
            throw BookingException::changedMeanwhile();
        }

        $this->ensureMovable($booking, $salon, $move, $now);

        if ($move->byClient && ! $this->keepsPromotion($booking, $move->startsAt->getTimestamp(), $salon)) {
            throw BookingException::promotionNotValidThen((string) $booking->promotion_name);
        }

        if ($target === null || ! $this->canTake($target, $booking)) {
            throw BookingException::staffCannotTake();
        }

        $start = $move->startsAt->getTimestamp();

        if ($target->id === $booking->staff_id && $start === $booking->starts_at->getTimestamp()) {
            return $booking;
        }

        $shape = $booking->shape();
        $offered = $this->availability->slots(
            $salon,
            $target,
            $shape,
            $move->startsAt->setTimezone($salon->timezone),
            $this->level($booking, $move->byClient),
            $now,
            $booking->id,
            underLock: true,
        );

        if (! in_array($start, $offered, true)) {
            throw BookingException::slotTaken();
        }

        $previous = $booking->starts_at->setTimezone($salon->timezone);
        $booking->staff_id = $target->id;
        $booking->starts_at = CarbonImmutable::createFromTimestampUTC($start);
        $booking->ends_at = CarbonImmutable::createFromTimestampUTC($shape->endsAt($start));
        $booking->save();

        $this->segments->handle($booking, $shape);
        $this->record($booking, $move, $previous);

        return $booking;
    }

    private function ensureMovable(Booking $booking, Salon $salon, BookingMove $move, CarbonImmutable $now): void
    {
        if (! $this->isOpen($booking, $now)) {
            throw BookingException::cannotChange($booking->effectiveStatus($now)->label());
        }

        if (! $salon->isWritable()) {
            throw BookingException::bookingsPaused();
        }

        if (! $move->byClient) {
            return;
        }

        if (! $salon->accepts_online_bookings) {
            throw BookingException::bookingsPaused();
        }

        if (! $this->hasNotice($booking, $salon, $now)) {
            throw BookingException::tooLateToMove($salon->cancel_window_hours);
        }

        // Moves are the events that leave the status as it was.
        $moves = BookingEvent::query()
            ->where('booking_id', $booking->id)
            ->where('changed_by', $move->actor?->id)
            ->whereColumn('from_status', 'to_status')
            ->count();

        if ($moves >= self::MAX_BY_CLIENT) {
            throw BookingException::tooManyMoves();
        }
    }

    private function isMovable(Booking $booking, Salon $salon, bool $byClient, CarbonImmutable $now): bool
    {
        return $this->isOpen($booking, $now)
            && $salon->isWritable()
            && (! $byClient || ($salon->accepts_online_bookings && $this->hasNotice($booking, $salon, $now)));
    }

    /**
     * Still waiting to happen: pending with its hold alive, or confirmed.
     */
    private function isOpen(Booking $booking, CarbonImmutable $now): bool
    {
        return in_array($booking->effectiveStatus($now), [BookingStatus::Pending, BookingStatus::Confirmed], true);
    }

    /**
     * The same notice the salon asks for a cancellation.
     */
    private function hasNotice(Booking $booking, Salon $salon, CarbonImmutable $now): bool
    {
        return $now->addHours($salon->cancel_window_hours)->lessThanOrEqualTo($booking->starts_at);
    }

    /**
     * The booking's own professional, or another active one of the same
     * salon who performs every service it has.
     */
    private function canTake(Staff $staff, Booking $booking): bool
    {
        if ($staff->salon_id !== $booking->salon_id || ! $staff->is_active) {
            return false;
        }

        if ($staff->id === $booking->staff_id) {
            return true;
        }

        $services = $booking->services()->get();

        // A service deleted since then cannot be checked: it stays with
        // whoever took it.
        if ($services->contains(fn (BookingService $service) => $service->service_id === null)) {
            return false;
        }

        $serviceIds = $services->pluck('service_id')->unique();
        $performed = DB::table('service_staff')
            ->where('staff_id', $staff->id)
            ->whereIn('service_id', $serviceIds)
            ->count();

        return $performed === $serviceIds->count();
    }

    /**
     * The team moves a booking into any of the professional's hours; a
     * client only into the ones they could book themselves today.
     */
    private function level(Booking $booking, bool $byClient): ClientLevel
    {
        if (! $byClient) {
            return ClientLevel::Team;
        }

        return SalonClient::withoutGlobalScope('salon')->find($booking->salon_client_id)?->level() ?? ClientLevel::Standard;
    }

    private function record(Booking $booking, BookingMove $move, CarbonImmutable $previous): void
    {
        $event = new BookingEvent;
        $event->booking_id = $booking->id;
        $event->from_status = $booking->status;
        $event->to_status = $booking->status;
        $event->changed_by = $move->actor?->id;
        $event->note = 'Movida desde el '.$previous->format('d/m/Y H:i');
        $event->save();
    }
}
