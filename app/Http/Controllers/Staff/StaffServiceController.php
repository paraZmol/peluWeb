<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\SyncStaffServices;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Http\Requests\Staff\StaffServicesRequest;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;

class StaffServiceController extends SalonAreaController
{
    /**
     * Professionals decide how long each service takes them; prices stay
     * with whoever manages the salon's services.
     */
    public function update(StaffServicesRequest $request, Staff $staff, SyncStaffServices $sync): RedirectResponse
    {
        $this->authorize('update', $staff);
        $canSetPrices = $request->user()?->can('manageServices', $this->currentSalon()) ?? false;

        $sync->handle($staff, $request->entries(), $canSetPrices);
        $this->toast('Servicios guardados.');

        return to_route('staff.schedule.show', $staff);
    }
}
