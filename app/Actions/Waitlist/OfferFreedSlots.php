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

/**
 * Goes over every waiting list and tells the next person in line when
 * there is a free time for what they asked. One person per salon and day
 * at a time: whoever let a turn go by waits behind the rest; among
 * equals, VIPs first and then by arrival. Run every minute: it does not care why the time opened up.
 */
class OfferFreedSlots
{
    public function __construct(private readonly FindAvailableSlots $slots) {}

    /**
     * @return int how many people were told
     */
    public function handle(CarbonImmutable $now): int
    {
        $told = 0;
        $active = WaitlistEntry::withoutGlobalScope('salon')->active()->with('client.user')->orderBy('id')->get();

        foreach ($active->groupBy('salon_id') as $salonId => $entries) {
            $salon = Salon::query()->find($salonId);

            if ($salon === null || ! $salon->isWritable() || ! $salon->accepts_online_bookings) {
                continue;
            }

            $days = $this->settle($entries, $salon, $now)->groupBy(fn (WaitlistEntry $entry): string => $entry->date->toDateString());

            foreach ($days as $day) {
                $told += $this->tellNext($day, $salon, $now) ? 1 : 0;
            }
        }

        return $told;
    }

    /**
     * Closes what is over: days gone by, people who already booked, and
     * turns that ran out (back in line, after the rest).
     *
     * @param  Collection<int, WaitlistEntry>  $entries
     * @return Collection<int, WaitlistEntry> the ones still in line or with a turn
     */
    private function settle(Collection $entries, Salon $salon, CarbonImmutable $now): Collection
    {
        $today = $now->setTimezone($salon->timezone)->toDateString();

        foreach ($entries as $entry) {
            if ($entry->date->toDateString() < $today) {
                $entry->status = WaitlistStatus::Expired;
            } elseif ($this->hasBooked($entry, $salon)) {
                $entry->status = WaitlistStatus::Booked;
            } elseif ($entry->status === WaitlistStatus::Offered && $entry->offer_expires_at?->lessThanOrEqualTo($now)) {
                $entry->status = WaitlistStatus::Waiting;
                $entry->offer_expires_at = null;
            }

            if ($entry->isDirty()) {
                $entry->save();
            }
        }

        return $entries->filter(fn (WaitlistEntry $entry): bool => $entry->status->isActive())->values();
    }

    /**
     * A booking of theirs that day, made after they joined the list.
     */
    private function hasBooked(WaitlistEntry $entry, Salon $salon): bool
    {
        $day = CarbonImmutable::parse($entry->date->toDateString(), $salon->timezone);

        return Booking::withoutGlobalScope('salon')
            ->where('salon_client_id', $entry->salon_client_id)
            ->where('starts_at', '>=', $day->utc())
            ->where('starts_at', '<', $day->addDay()->utc())
            ->where('created_at', '>=', $entry->created_at)
            ->holdingSlot()
            ->exists();
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
            if ($this->hasFreeTime($entry, $salon, $now)) {
                $entry->status = WaitlistStatus::Offered;
                $entry->offers_count++;
                $entry->offered_at = $now;
                $entry->offer_expires_at = $now->addMinutes(WaitlistEntry::OFFER_MINUTES);
                $entry->save();
                $this->notify($entry, $salon);

                return true;
            }
        }

        return false;
    }

    private function hasFreeTime(WaitlistEntry $entry, Salon $salon, CarbonImmutable $now): bool
    {
        $staff = $entry->staff_id === null ? null : Staff::withoutGlobalScope('salon')->where('salon_id', $salon->id)->find($entry->staff_id);
        $day = CarbonImmutable::parse($entry->date->toDateString(), $salon->timezone);

        try {
            return $this->slots->handle($salon, $entry->selections, $staff, $day, $entry->client->level(), $now) !== [];
        } catch (BookingException) {
            // The service or the professional is gone: nothing to wait for.
            $entry->status = WaitlistStatus::Cancelled;
            $entry->save();

            return false;
        }
    }

    private function notify(WaitlistEntry $entry, Salon $salon): void
    {
        $user = $entry->client->user;

        if ($user === null || ! $user->hasVerifiedEmail()) {
            return;
        }

        $day = CarbonImmutable::parse($entry->date->toDateString(), $salon->timezone);

        $user->notify(new WaitlistNotification(
            salon: $salon->name,
            day: ucfirst($day->isoFormat('dddd D [de] MMMM')),
            until: (string) $entry->offer_expires_at?->setTimezone($salon->timezone)->isoFormat('h:mm a'),
            url: route('salons.booking.create', ['salon' => $salon->slug, 'fecha' => $day->toDateString(), 'servicio' => $entry->selections[0]['service_id'] ?? null]),
        ));
    }
}
