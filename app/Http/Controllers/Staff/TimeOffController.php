<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\AddTimeOff;
use App\Enums\TimeOffReason;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Http\Requests\Staff\TimeOffRequest;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use Illuminate\Http\RedirectResponse;

class TimeOffController extends SalonAreaController
{
    public function store(TimeOffRequest $request, Staff $staff, AddTimeOff $add): RedirectResponse
    {
        $this->authorize('update', $staff);
        [$startsAt, $endsAt] = $request->period($this->currentSalon()->timezone);

        $add->handle($staff, $startsAt, $endsAt, $request->enum('reason', TimeOffReason::class), $request->validated('note'));
        $this->toast('Ausencia guardada.');

        return to_route('staff.schedule.show', $staff);
    }

    public function destroy(Staff $staff, StaffTimeOff $timeOff): RedirectResponse
    {
        $this->authorize('update', $staff);
        // The absence must be this professional's, not just this salon's.
        abort_if($timeOff->staff_id !== $staff->id, 404);

        $timeOff->delete();
        $this->toast('Ausencia eliminada.');

        return to_route('staff.schedule.show', $staff);
    }
}
