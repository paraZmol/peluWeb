<?php

namespace App\Actions\Booking;

use App\Enums\ClientLevel;
use App\Exceptions\BookingException;
use App\Models\Salon;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FindAvailableSlots
{
    public function __construct(
        private readonly QuoteBooking $quote,
        private readonly StaffAvailability $availability,
    ) {}

    /**
     * Starts a client can pick on one day, with who could take each.
     *
     * @param  list<array{service_id: int, variant_id: int|null}>  $selections
     * @param  Staff|null  $staff  null = anyone who performs every service
     * @param  CarbonImmutable  $localDate  any instant of the day, in the salon's timezone
     * @return array<int, list<int>> start (Unix, UTC) => staff ids, sorted by start
     */
    public function handle(
        Salon $salon,
        array $selections,
        ?Staff $staff,
        CarbonImmutable $localDate,
        ClientLevel $level,
        CarbonImmutable $now,
    ): array {
        $quotes = $this->quote->handle($salon, $selections, $this->candidates($salon, $staff));

        if ($quotes === []) {
            throw BookingException::nobodyPerformsThem();
        }

        $slots = [];

        foreach ($this->candidates($salon, $staff)->only(array_keys($quotes)) as $person) {
            foreach ($this->availability->slots($salon, $person, $quotes[$person->id]->shape(), $localDate, $level, $now) as $start) {
                $slots[$start][] = $person->id;
            }
        }

        ksort($slots);

        return $slots;
    }

    /**
     * Active professionals of the salon (or just the chosen one), keyed
     * by id.
     *
     * @return Collection<int, Staff>
     */
    public function candidates(Salon $salon, ?Staff $staff): Collection
    {
        return Staff::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('is_active', true)
            ->when($staff !== null, fn ($query) => $query->whereKey($staff?->id))
            ->orderBy('position')
            ->get()
            ->keyBy('id');
    }
}
