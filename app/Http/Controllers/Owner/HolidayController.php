<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Salon\AddHoliday;
use App\Http\Requests\Owner\HolidayRequest;
use App\Models\SalonHoliday;
use Illuminate\Http\RedirectResponse;

/**
 * Days the whole salon closes. `{holiday}` is filtered by the current
 * salon (BelongsToSalon), so another salon's resolves to 404.
 */
class HolidayController extends SalonAreaController
{
    public function store(HolidayRequest $request, AddHoliday $add): RedirectResponse
    {
        $add->handle(
            $this->salon('manageTeam'),
            $request->validated('date'),
            $request->string('name')->trim()->toString(),
        );
        $this->toast('Día de cierre guardado.');

        return to_route('staff.index');
    }

    public function destroy(SalonHoliday $holiday): RedirectResponse
    {
        $this->salon('manageTeam');
        $holiday->delete();
        $this->toast('Día de cierre eliminado.');

        return to_route('staff.index');
    }
}
