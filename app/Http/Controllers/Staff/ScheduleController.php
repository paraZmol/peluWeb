<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\ReplaceWeeklySchedule;
use App\Enums\ScheduleAccess;
use App\Enums\TimeOffReason;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Http\Requests\Staff\WeeklyScheduleRequest;
use App\Http\Resources\StaffEditorResource;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The screen where a professional (or an owner, for anyone) edits working
 * hours, time off and services. `{staff}` is filtered by the current salon
 * (BelongsToSalon); StaffPolicy decides who may edit whom.
 */
class ScheduleController extends SalonAreaController
{
    /**
     * "Mi horario": the professional profile of whoever is signed in.
     */
    public function mine(Request $request): RedirectResponse
    {
        $staff = Staff::query()->where('user_id', $request->user()?->id)->first();

        if ($staff === null) {
            $this->toast('Aún no tienes un perfil de profesional en este salón. Pídeselo a quien lo administra.', 'error');

            return to_route('dashboard');
        }

        return to_route('staff.schedule.show', $staff);
    }

    public function show(Request $request, Staff $staff): Response
    {
        $this->authorize('update', $staff);
        $salon = $this->currentSalon();

        $staff->load(['user', 'schedules', 'services', 'timeOff' => fn ($timeOff) => $timeOff->where('ends_at', '>', now())]);

        return Inertia::render('staff/schedule', [
            'staff' => new StaffEditorResource($staff, $salon->services()->orderBy('position')->get(), $salon->timezone),
            'accessLevels' => collect(ScheduleAccess::cases())
                ->map(fn (ScheduleAccess $access) => ['value' => $access->value, 'label' => $access->label(), 'hint' => $access->hint()]),
            'timeOffReasons' => collect(TimeOffReason::cases())
                ->map(fn (TimeOffReason $reason) => ['value' => $reason->value, 'label' => $reason->label()]),
            'canManageTeam' => $request->user()?->can('manageTeam', $salon) ?? false,
            'canSetPrices' => $request->user()?->can('manageServices', $salon) ?? false,
            'today' => $salon->localNow()->toDateString(),
        ]);
    }

    public function update(WeeklyScheduleRequest $request, Staff $staff, ReplaceWeeklySchedule $replace): RedirectResponse
    {
        $this->authorize('update', $staff);
        $replace->handle($staff, $request->ranges());
        $this->toast('Horario guardado.');

        return to_route('staff.schedule.show', $staff);
    }
}
