<?php

namespace App\Actions\Staff;

use App\Models\Service;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class SyncStaffServices
{
    /**
     * Save the services of a professional, with their own working time and
     * price when they differ from the service's.
     *
     * Who manages the salon's services ($canManage) decides which services
     * the professional performs and at what price. Without it, only the
     * working time of the services already assigned changes: "each one
     * decides how long it takes them", nothing else.
     *
     * @param  list<array{service_id: int, active_minutes: int|null, price_cents: int|null}>  $entries
     */
    public function handle(Staff $staff, array $entries, bool $canManage): void
    {
        DB::transaction(function () use ($staff, $entries, $canManage): void {
            // Same lock as booking: services never change mid-reservation,
            // and the prices read below cannot go stale before the write.
            Staff::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($staff->id);

            // Deleted services are left alone: restoring one brings back
            // exactly who performed it and their own time and price.
            $salonServiceIds = Service::withoutGlobalScope('salon')->where('salon_id', $staff->salon_id)->pluck('id');
            $current = DB::table('service_staff')->where('staff_id', $staff->id)->get()->keyBy('service_id');
            $pivots = [];

            foreach ($entries as $entry) {
                $id = $entry['service_id'];

                // A service of another salon is ignored, as if it did not exist.
                if (! $salonServiceIds->contains($id) || (! $canManage && ! $current->has($id))) {
                    continue;
                }

                $pivots[$id] = [
                    'active_minutes' => $entry['active_minutes'],
                    'price_cents' => $canManage ? $entry['price_cents'] : $current->get($id)?->price_cents,
                ];
            }

            $staff->services()->syncWithoutDetaching($pivots);

            if ($canManage) {
                $staff->services()->detach($salonServiceIds->diff(array_keys($pivots))->all());
            }
        });
    }
}
