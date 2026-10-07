<?php

namespace App\Actions\Staff;

use App\Models\Service;
use App\Models\Staff;

class SyncStaffServices
{
    /**
     * Set which services a professional performs, with their own working
     * time and price when they differ from the service's.
     *
     * Prices are only written when $canSetPrices: otherwise each service
     * keeps the price it already had for this professional.
     *
     * @param  list<array{service_id: int, active_minutes: int|null, price_cents: int|null}>  $entries
     */
    public function handle(Staff $staff, array $entries, bool $canSetPrices): void
    {
        $ownServiceIds = Service::withoutGlobalScope('salon')
            ->where('salon_id', $staff->salon_id)
            ->whereIn('id', array_column($entries, 'service_id'))
            ->pluck('id')
            ->flip();

        $currentPrices = $staff->services()->pluck('service_staff.price_cents', 'services.id');
        $pivots = [];

        foreach ($entries as $entry) {
            // A service of another salon is ignored, as if it did not exist.
            if (! $ownServiceIds->has($entry['service_id'])) {
                continue;
            }

            $pivots[$entry['service_id']] = [
                'active_minutes' => $entry['active_minutes'],
                'price_cents' => $canSetPrices ? $entry['price_cents'] : $currentPrices->get($entry['service_id']),
            ];
        }

        $staff->services()->sync($pivots);
    }
}
