<?php

namespace App\Actions\Waitlist;

use App\Actions\Booking\FindAvailableSlots;
use App\Exceptions\WaitlistException;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Staff;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Puts someone on a salon's waiting list for a day with no free time for
 * what they want. The caller made sure the client record is theirs.
 */
class JoinWaitlist
{
    public function __construct(private readonly FindAvailableSlots $slots) {}

    /**
     * Two taps at once from a new account both try to create it: the
     * second one finds the first.
     */
    private function firstRecord(SalonClient $client): SalonClient
    {
        try {
            $client->save();

            return $client;
        } catch (UniqueConstraintViolationException) {
            return SalonClient::withoutGlobalScope('salon')->where('salon_id', $client->salon_id)->where('user_id', $client->user_id)->firstOrFail();
        }
    }

    /**
     * @param  list<array{service_id: int, variant_id: int|null}>  $selections
     * @param  CarbonImmutable  $localDate  the day, on the salon's clock
     */
    public function handle(SalonClient $client, array $selections, ?Staff $staff, CarbonImmutable $localDate): WaitlistEntry
    {
        $salon = Salon::query()->findOrFail($client->salon_id);
        $now = CarbonImmutable::now();
        $today = $now->setTimezone($salon->timezone)->startOfDay();

        if ($localDate->lessThan($today) || $localDate->greaterThan($today->addDays($salon->max_days_ahead))) {
            throw WaitlistException::dayOutOfRange();
        }

        // Also proves the selection is real: it throws when nobody performs it.
        if ($this->slots->handle($salon, $selections, $staff, $localDate, $client->level(), $now) !== []) {
            throw WaitlistException::thereAreTimes();
        }

        // Someone who never booked here has no record yet: this is their first.
        if (! $client->exists) {
            $client = $this->firstRecord($client);
        }

        return DB::transaction(function () use ($client, $selections, $staff, $localDate): WaitlistEntry {
            // The person's own row: two taps at once count the same places.
            SalonClient::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($client->id);
            $mine = WaitlistEntry::withoutGlobalScope('salon')->where('salon_client_id', $client->id)->active();

            if ((clone $mine)->whereDate('date', $localDate->toDateString())->exists()) {
                throw WaitlistException::alreadyWaiting();
            }

            if ($mine->count() >= WaitlistEntry::MAX_PER_CLIENT) {
                throw WaitlistException::tooMany(WaitlistEntry::MAX_PER_CLIENT);
            }

            $thatDay = WaitlistEntry::withoutGlobalScope('salon')->where('salon_id', $client->salon_id)->active()->whereDate('date', $localDate->toDateString());

            if ($thatDay->count() >= WaitlistEntry::MAX_PER_DAY) {
                throw WaitlistException::dayIsFull();
            }

            $entry = new WaitlistEntry;
            $entry->salon_id = $client->salon_id;
            $entry->salon_client_id = $client->id;
            $entry->staff_id = $staff?->id;
            $entry->date = $localDate;
            $entry->selections = $selections;
            $entry->save();

            return $entry;
        });
    }
}
