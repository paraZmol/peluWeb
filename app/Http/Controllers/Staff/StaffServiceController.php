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
     * Professionals decide how long each service takes them; which
     * services they perform and at what price stays with whoever manages
     * the salon's services.
     */
    public function update(StaffServicesRequest $request, Staff $staff, SyncStaffServices $sync): RedirectResponse
    {
        $this->authorize('update', $staff);
        $canManage = $request->user()?->can('manageServices', $this->currentSalon()) ?? false;

        $sync->handle($staff, $request->entries(), $canManage);
        $this->toast('Servicios guardados.');

        return to_route('staff.schedule.show', $staff);
    }
}
