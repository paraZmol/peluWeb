<?php

namespace App\Actions\Staff;

use App\Models\Salon;
use App\Models\Staff;

class AssignServicesToTeam
{
    /**
     * A service nobody performs cannot be booked, so new services start
     * assigned to every professional; the owner unticks who does not do it.
     *
     * @param  list<int>  $serviceIds
     */
    public function handle(Salon $salon, array $serviceIds): void
    {
        if ($serviceIds === []) {
            return;
        }

        Staff::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->get()
            ->each(fn (Staff $staff) => $staff->services()->syncWithoutDetaching($serviceIds));
    }
}
