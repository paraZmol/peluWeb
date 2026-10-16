<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Staff\RemoveStaff;
use App\Actions\Staff\SaveStaff;
use App\Http\Requests\Owner\StaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\SalonHoliday;
use App\Models\SalonMember;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `{staff}` bindings are filtered by the current salon (BelongsToSalon),
 * so another salon's professional resolves to 404.
 */
class StaffController extends SalonAreaController
{
    public function index(): Response
    {
        $salon = $this->salon('manageTeam');
        $staff = $salon->staff()->with(['user', 'schedules'])->withCount('services')->get();

        return Inertia::render('owner/staff/index', [
            'staff' => StaffResource::collection($staff)->resolve(),
            'linkableMembers' => $this->membersWithoutProfile($salon, $staff),
            'holidays' => $salon->holidays()
                ->whereDate('date', '>=', $salon->localNow()->toDateString())
                ->get()
                ->map(fn (SalonHoliday $holiday) => [
                    'id' => $holiday->id,
                    'date' => $holiday->date->format('Y-m-d'),
                    'name' => $holiday->name,
                ]),
            'today' => $salon->localNow()->toDateString(),
            // What the plan allows: only professionals with an agenda count.
            'agendaLimit' => [
                'withAgenda' => $staff->where('is_active', true)->count(),
                'max' => $salon->staffLimit(),
                'planName' => $salon->currentPlan()->name,
            ],
        ]);
    }

    public function store(StaffRequest $request, SaveStaff $save): RedirectResponse
    {
        $staff = $save->handle($this->salon('manageTeam'), null, $request->staffAttributes(), $request->userId());
        $this->toast("Agregamos a {$staff->name}. Ahora define su horario.");

        return to_route('staff.schedule.show', $staff);
    }

    public function update(StaffRequest $request, Staff $staff, SaveStaff $save): RedirectResponse
    {
        $save->handle($this->salon('manageTeam'), $staff, $request->staffAttributes(), $request->userId($staff->user_id));
        $this->toast('Cambios guardados.');

        return to_route('staff.index');
    }

    public function destroy(Staff $staff, RemoveStaff $remove): RedirectResponse
    {
        $this->salon('manageTeam');
        $remove->handle($staff);
        $this->toast("{$staff->name} ya no aparece entre tus profesionales.");

        return to_route('staff.index');
    }

    /**
     * Team accounts that can still be linked to a professional.
     *
     * @param  Collection<int, Staff>  $staff
     * @return Collection<int, array{userId: int, name: string, email: string}>
     */
    private function membersWithoutProfile(Salon $salon, Collection $staff): Collection
    {
        return $salon->members()
            ->with('user')
            ->whereNotIn('user_id', $staff->pluck('user_id')->filter())
            ->get()
            ->map(fn (SalonMember $member) => [
                'userId' => $member->user_id,
                'name' => $member->user->name,
                'email' => $member->user->email,
            ])
            ->values();
    }
}
