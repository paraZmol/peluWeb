<?php

namespace App\Actions\Waitlist;

use App\Actions\Booking\FindAvailableSlots;
use App\Enums\WaitlistStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Goes over every waiting list and tells the next person in line when
 * there is a free time for what they asked. One person per salon and day
 * at a time: whoever let a turn go by waits behind the rest; among
 * equals, VIPs first and then by arrival. Run every minute: it does not
 * care why the time opened up.
 */
class OfferFreedSlots
{
    /**
     * Free-time searches already made in this run, by what was asked:
     * true or false, or null when what was asked no longer exists.
     *
     * @var array<string, bool|null>
     */
    private array $searched = [];

    public function __construct(private readonly FindAvailableSlots $slots) {}

    /**
     * @return int how many people were told
     */
    public function handle(CarbonImmutable $now): int
    {
        $told = 0;
        $salonIds = WaitlistEntry::withoutGlobalScope('salon')->active()->distinct()->orderBy('salon_id')->pluck('salon_id');

        foreach ($salonIds as $salonId) {
            $this->searched = [];

            // One salon's trouble must not stop the lists of all the others.
            try {
                $told += $this->salon((int) $salonId, $now);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $told;
    }

    private function salon(int $salonId, CarbonImmutable $now): int
    {
        $salon = Salon::query()->find($salonId);

        if ($salon === null || ! $salon->isWritable() || ! $salon->accepts_online_bookings) {
            return 0;
        }

        $entries = WaitlistEntry::withoutGlobalScope('salon')->where('salon_id', $salonId)->active()->with('client.user')->orderBy('id')->get();
        $told = 0;

        foreach ($this->settle($entries, $salon, $now)->groupBy(fn (WaitlistEntry $entry): string => $entry->date->toDateString()) as $day) {
            $told += $this->tellNext($day, $salon, $now) ? 1 : 0;
        }

        return $told;
    }

    /**
     * Closes what is over: days gone by, people who already booked or
     * whose account is gone, and turns that ran out (back in line after
     * the rest, or out after too many).
     *
     * @param  Collection<int, WaitlistEntry>  $entries
     * @return Collection<int, WaitlistEntry> the ones still in line or with a turn
     */
    private function settle(Collection $entries, Salon $salon, CarbonImmutable $now): Collection
    {
        $today = $now->setTimezone($salon->timezone)->toDateString();
        $booked = $this->bookedDays($entries, $salon);

        foreach ($entries as $entry) {
            $ranOut = $entry->status === WaitlistStatus::Offered && $entry->offer_expires_at?->lessThanOrEqualTo($now);

            $next = match (true) {
                $entry->date->toDateString() < $today => WaitlistStatus::Expired,
                // Nobody to tell: the account was deleted or lost its address.
                $entry->client->user?->hasVerifiedEmail() !== true => WaitlistStatus::Cancelled,
                in_array($entry->date->toDateString(), $booked[$entry->salon_client_id][$entry->id] ?? [], true) => WaitlistStatus::Booked,
                $ranOut && $entry->offers_count >= WaitlistEntry::MAX_OFFERS => WaitlistStatus::Expired,
                $ranOut => WaitlistStatus::Waiting,
                default => $entry->status,
            };

            if ($next !== $entry->status) {
                $this->move($entry, $next, $next === WaitlistStatus::Waiting ? ['offer_expires_at' => null] : []);
            }
        }

        return $entries->filter(fn (WaitlistEntry $entry): bool => $entry->status->isActive())->values();
    }

    /**
     * The days each person booked after joining the list, with one query
     * for the whole salon.
     *
     * @param  Collection<int, WaitlistEntry>  $entries
     * @return array<int, array<int, list<string>>> client record => entry => local days
     */
    private function bookedDays(Collection $entries, Salon $salon): array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        $bookings = Booking::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->whereIn('salon_client_id', $entries->pluck('salon_client_id')->unique())
            ->where('starts_at', '>=', CarbonImmutable::now()->subDay())
            ->holdingSlot()
            ->get(['id', 'salon_client_id', 'starts_at', 'created_at'])
            ->groupBy('salon_client_id');

        $days = [];

        foreach ($entries as $entry) {
            foreach ($bookings->get($entry->salon_client_id) ?? [] as $booking) {
                if ($booking->created_at?->greaterThanOrEqualTo($entry->created_at) === true) {
                    $days[$entry->salon_client_id][$entry->id][] = $booking->starts_at->setTimezone($salon->timezone)->toDateString();
                }
            }
        }

        return $days;
    }

    /**
     * @param  Collection<int, WaitlistEntry>  $day  the active entries of one salon and day
     */
    private function tellNext(Collection $day, Salon $salon, CarbonImmutable $now): bool
    {
        // Someone has the turn: the rest wait for them to book or let it go.
        if ($day->contains(fn (WaitlistEntry $entry): bool => $entry->status === WaitlistStatus::Offered)) {
            return false;
        }

        $line = $day->sort(fn (WaitlistEntry $a, WaitlistEntry $b): int => [$a->offers_count, (int) $b->client->is_vip, $a->id] <=> [$b->offers_count, (int) $a->client->is_vip, $b->id]);

        foreach ($line as $entry) {
            if (! $this->hasFreeTime($entry, $salon, $now)) {
                continue;
            }

            $turn = [
                'offers_count' => $entry->offers_count + 1,
                'offered_at' => $now,
                'offer_expires_at' => $now->addMinutes(WaitlistEntry::OFFER_MINUTES),
            ];

            // Only if they are still waiting: they may have left a second ago.
            if ($this->move($entry, WaitlistStatus::Offered, $turn)) {
                $this->notify($entry->refresh(), $salon);

                return true;
            }
        }

        return false;
    }

    /**
     * Changes the place in the database only if it still is what this run
     * read: a person leaving the list at the same moment wins.
     *
     * @param  array<string, mixed>  $extra
     */
    private function move(WaitlistEntry $entry, WaitlistStatus $next, array $extra = []): bool
    {
        $changed = WaitlistEntry::withoutGlobalScope('salon')
            ->whereKey($entry->id)
            ->where('status', $entry->status)
            ->update(['status' => $next, ...$extra]);

        // What this run goes on with, either way: a place that changed
        // under it is no longer in line here.
        $entry->setRawAttributes([...$entry->getAttributes(), 'status' => ($changed === 1 ? $next : WaitlistStatus::Cancelled)->value], true);

        return $changed === 1;
    }

    private function hasFreeTime(WaitlistEntry $entry, Salon $salon, CarbonImmutable $now): bool
    {
        $staff = $entry->staff_id === null ? null : Staff::withoutGlobalScope('salon')->where('salon_id', $salon->id)->where('is_active', true)->find($entry->staff_id);

        // They asked for someone who no longer works here: nothing to wait for.
        if ($entry->staff_id !== null && $staff === null) {
            $this->move($entry, WaitlistStatus::Cancelled);

            return false;
        }

        $level = $entry->client->level();
        $key = implode('|', [$entry->date->toDateString(), $entry->staff_id ?? 0, json_encode($entry->selections), $level->value]);

        if (! array_key_exists($key, $this->searched)) {
            $day = CarbonImmutable::parse($entry->date->toDateString(), $salon->timezone);

            try {
                $this->searched[$key] = $this->slots->handle($salon, $entry->selections, $staff, $day, $level, $now) !== [];
            } catch (BookingException) {
                // The service is gone: the same for everyone who asked for it.
                $this->searched[$key] = null;
            }
        }

        if ($this->searched[$key] === null) {
            $this->move($entry, WaitlistStatus::Cancelled);
        }

        return $this->searched[$key] === true;
    }

    private function notify(WaitlistEntry $entry, Salon $salon): void
    {
        $day = CarbonImmutable::parse($entry->date->toDateString(), $salon->timezone);

        $entry->client->user?->notify(new WaitlistNotification(
            salon: $salon->name,
            day: ucfirst($day->isoFormat('dddd D [de] MMMM')),
            until: (string) $entry->offer_expires_at?->setTimezone($salon->timezone)->isoFormat('h:mm a'),
            url: route('salons.booking.create', ['salon' => $salon->slug, 'fecha' => $day->toDateString(), 'servicio' => $entry->selections[0]['service_id'] ?? null]),
        ));
    }
}
