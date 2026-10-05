<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Team\RemoveMember;
use App\Actions\Team\UpdateMember;
use App\Enums\SalonRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\UpdateMemberRequest;
use App\Models\SalonMember;
use App\Support\CurrentSalon;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MemberController extends Controller
{
    public function update(UpdateMemberRequest $request, CurrentSalon $currentSalon, SalonMember $member, UpdateMember $updateMember): RedirectResponse
    {
        $this->ensureBelongsToCurrentSalon($member, $currentSalon);
        $this->authorize('update', $member);

        $updateMember->handle($member, $request->enum('role', SalonRole::class), $request->permissions());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cambios guardados.']);

        return to_route('team.index');
    }

    public function destroy(CurrentSalon $currentSalon, SalonMember $member, RemoveMember $removeMember): RedirectResponse
    {
        $this->ensureBelongsToCurrentSalon($member, $currentSalon);
        $this->authorize('delete', $member);

        $removeMember->handle($member);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La persona ya no forma parte del equipo.']);

        return to_route('team.index');
    }

    /**
     * Memberships are not salon-scoped models, so the check is explicit:
     * a member of another salon must look like it does not exist.
     */
    private function ensureBelongsToCurrentSalon(SalonMember $member, CurrentSalon $currentSalon): void
    {
        abort_if($member->salon_id !== $currentSalon->id(), 404);
    }
}
