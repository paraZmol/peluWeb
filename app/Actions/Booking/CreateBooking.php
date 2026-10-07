<?php

namespace App\Actions\Booking;

use App\Data\Booking\BookingQuote;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\ClientLevel;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingSegment;
use App\Models\BookingService;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only way a booking comes to exist. Double booking is impossible
 * because every attempt runs in a transaction that first locks the
 * professional's row (so bookings of one professional are serialized;
 * other professionals are not blocked) and then asks the very same
 * availability code that drew the time picker.
 */
class CreateBooking
{
    public const int MAX_UPCOMING_PER_CLIENT = 5;

    /** Letters and digits that cannot be confused when read aloud or typed. */
    private const string CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly FindAvailableSlots $finder,
        private readonly QuoteBooking $quote,
        private readonly StaffAvailability $availability,
    ) {}

    public function handle(NewBooking $data): Booking
    {
        $now = CarbonImmutable::now();
        $this->ensureAccepted($data, $now);

        $candidates = $this->finder->candidates($data->salon, $data->staff);
        $quotes = $this->quote->handle($data->salon, $data->selections, $candidates);

        if ($quotes === []) {
            throw BookingException::nobodyPerformsThem();
        }

        foreach ($this->leastBusyFirst($candidates->only(array_keys($quotes)), $data) as $staff) {
            $booking = DB::transaction(fn () => $this->attempt($data, $staff, $now));

            if ($booking !== null) {
                return $booking;
            }
        }

        throw BookingException::slotTaken();
    }

    /**
     * Book with this professional if, once their row is locked, the start
     * is still one the picker would offer. Null means "not with them".
     */
    private function attempt(NewBooking $data, Staff $staff, CarbonImmutable $now): ?Booking
    {
        $locked = Staff::withoutGlobalScope('salon')
            ->where('salon_id', $data->salon->id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->find($staff->id);

        if ($locked === null) {
            return null;
        }

        // Quoted again under the lock: services and prices cannot change
        // while it is held (SyncStaffServices takes the same lock).
        $quote = $this->quote->handle($data->salon, $data->selections, collect([$locked]))[$locked->id] ?? null;

        if ($quote === null) {
            return null;
        }

        $offered = $this->availability->slots(
            $data->salon,
            $locked,
            $quote->shape(),
            $data->startsAt->setTimezone($data->salon->timezone),
            $this->level($data),
            $now,
        );

        if (! in_array($data->startsAt->getTimestamp(), $offered, true)) {
            return null;
        }

        return $this->store($data, $locked, $quote, $now);
    }

    private function store(NewBooking $data, Staff $staff, BookingQuote $quote, CarbonImmutable $now): Booking
    {
        $start = $data->startsAt->getTimestamp();
        $shape = $quote->shape();
        $deposit = $this->depositCents($data, $quote->priceCents());

        $booking = new Booking;
        $booking->salon_id = $data->salon->id;
        $booking->code = $this->uniqueCode();
        $booking->salon_client_id = $data->client->id;
        $booking->staff_id = $staff->id;
        $booking->source = $data->source;
        $booking->starts_at = CarbonImmutable::createFromTimestampUTC($start);
        $booking->ends_at = CarbonImmutable::createFromTimestampUTC($shape->endsAt($start));
        $booking->is_vip = $data->client->is_vip;
        $booking->price_cents = $quote->priceCents();
        $booking->deposit_cents = $deposit;
        // Without a deposit there is nothing to wait for: it is confirmed.
        $booking->status = $deposit > 0 ? BookingStatus::Pending : BookingStatus::Confirmed;
        $booking->hold_expires_at = $deposit > 0 ? $now->addMinutes($data->salon->hold_minutes) : null;
        $booking->client_note = $data->note;
        $booking->created_by = $data->actor?->id;
        $booking->save();

        foreach ($quote->items as $position => $item) {
            $service = new BookingService;
            $service->booking_id = $booking->id;
            $service->service_id = $item->serviceId;
            $service->name = $item->name;
            $service->price_cents = $item->priceCents;
            $service->active_minutes = $item->activeMinutes;
            $service->idle_minutes = $item->idleMinutes;
            $service->finish_minutes = $item->finishMinutes;
            $service->position = $position;
            $service->save();
        }

        foreach ($shape->busyAt($start) as $stretch) {
            $segment = new BookingSegment;
            $segment->booking_id = $booking->id;
            $segment->staff_id = $staff->id;
            $segment->starts_at = CarbonImmutable::createFromTimestampUTC($stretch->start);
            $segment->ends_at = CarbonImmutable::createFromTimestampUTC($stretch->end);
            $segment->save();
        }

        $event = new BookingEvent;
        $event->booking_id = $booking->id;
        $event->to_status = $booking->status;
        $event->changed_by = $data->actor?->id;
        $event->save();

        return $booking;
    }

    private function ensureAccepted(NewBooking $data, CarbonImmutable $now): void
    {
        $online = $data->source === BookingSource::Client;

        if (! $data->salon->isWritable() || ($online && ! $data->salon->accepts_online_bookings)) {
            throw BookingException::bookingsPaused();
        }

        // Keeps one account from holding every slot of a small salon.
        $upcoming = Booking::withoutGlobalScope('salon')
            ->where('salon_client_id', $data->client->id)
            ->where('starts_at', '>', $now)
            ->holdingSlot($now)
            ->count();

        if ($online && $upcoming >= self::MAX_UPCOMING_PER_CLIENT) {
            throw BookingException::tooManyUpcoming(self::MAX_UPCOMING_PER_CLIENT);
        }
    }

    /**
     * With "anyone", try first who has fewer bookings that day, so the
     * work spreads across the team.
     *
     * @param  Collection<int, Staff>  $candidates
     * @return Collection<int, Staff>
     */
    private function leastBusyFirst(Collection $candidates, NewBooking $data): Collection
    {
        if ($candidates->count() < 2) {
            return $candidates;
        }

        $day = $data->startsAt->setTimezone($data->salon->timezone);

        $load = Booking::withoutGlobalScope('salon')
            ->whereIn('staff_id', $candidates->pluck('id'))
            ->whereBetween('starts_at', [$day->startOfDay()->utc(), $day->endOfDay()->utc()])
            ->holdingSlot()
            ->selectRaw('staff_id, count(*) as total')
            ->groupBy('staff_id')
            ->pluck('total', 'staff_id');

        return $candidates->sortBy(fn (Staff $staff) => [(int) $load->get($staff->id, 0), $staff->position])->values();
    }

    /**
     * The team books for whoever is in front of them, in any hours; a
     * client's own level decides what they may book online.
     */
    private function level(NewBooking $data): ClientLevel
    {
        return $data->source === BookingSource::Staff ? ClientLevel::Team : $data->client->level();
    }

    /**
     * Bookings made by the team carry no deposit: they are arranged in
     * person or by phone.
     */
    private function depositCents(NewBooking $data, int $priceCents): int
    {
        if ($data->source === BookingSource::Staff) {
            return 0;
        }

        return intdiv($priceCents * $data->salon->depositPercentFor($data->client->is_vip) + 50, 100);
    }

    private function uniqueCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < Booking::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (Booking::withoutGlobalScope('salon')->where('code', $code)->exists());

        return $code;
    }
}
