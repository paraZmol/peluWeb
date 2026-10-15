<?php

namespace App\Actions\Waitlist;

use App\Enums\SalonStatus;
use App\Enums\WaitlistStatus;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Builder;

/**
 * The places a person holds on waiting lists, across every salon. Theirs
 * are the ones of client records made by their own account.
 */
class MyWaitlist
{
    /**
     * @return Builder<WaitlistEntry>
     */
    public function query(User $user): Builder
    {
        return WaitlistEntry::withoutGlobalScope('salon')
            ->whereHas('client', fn (Builder $client) => $client->where('user_id', $user->id));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(User $user): array
    {
        $entries = $this->query($user)->active()->orderBy('date')->orderBy('id')->get();
        $salons = Salon::query()->whereKey($entries->pluck('salon_id')->unique())->get()->keyBy('id');
        $services = Service::withoutGlobalScope('salon')
            ->whereKey($entries->flatMap(fn (WaitlistEntry $entry) => array_column($entry->selections, 'service_id'))->unique())
            ->pluck('name', 'id');

        return $entries
            // A closed or suspended salon takes its list with it.
            ->filter(fn (WaitlistEntry $entry): bool => $salons->get($entry->salon_id)?->status !== SalonStatus::Suspended && $salons->has($entry->salon_id))
            ->map(function (WaitlistEntry $entry) use ($salons, $services): array {
                /** @var Salon $salon */
                $salon = $salons->get($entry->salon_id);
                $hasTurn = $entry->status === WaitlistStatus::Offered;

                return [
                    'id' => $entry->id,
                    'salonName' => $salon->name,
                    'date' => $entry->date->toDateString(),
                    'services' => collect($entry->selections)->map(fn (array $selection) => $services->get($selection['service_id']))->filter()->values()->all(),
                    'hasTurn' => $hasTurn,
                    // Until when the freed time is theirs before the next person is told.
                    'turnUntil' => $hasTurn ? $entry->offer_expires_at?->setTimezone($salon->timezone)->format('H:i') : null,
                    // The day and the first thing they asked for, already chosen in the wizard.
                    'bookUrl' => route('salons.booking.create', ['salon' => $salon->slug, 'fecha' => $entry->date->toDateString(), 'servicio' => $entry->selections[0]['service_id'] ?? null]),
                ];
            })->values()->all();
    }
}
