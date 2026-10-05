<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Team\InviteMember;
use App\Enums\SalonRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\InviteMemberRequest;
use App\Models\SalonInvitation;
use App\Support\CurrentSalon;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function store(InviteMemberRequest $request, CurrentSalon $currentSalon, InviteMember $inviteMember): RedirectResponse
    {
        $salon = $currentSalon->get();
        $this->authorize('manageTeam', $salon);

        $invitation = $inviteMember->handle(
            salon: $salon,
            inviter: $request->user(),
            email: $request->validated('email'),
            role: $request->enum('role', SalonRole::class),
            permissions: $request->permissions(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Invitación enviada a {$invitation->email}."]);

        return to_route('team.index');
    }

    /**
     * Route binding is filtered by the current salon (BelongsToSalon), so an
     * invitation from another salon resolves to 404.
     */
    public function destroy(CurrentSalon $currentSalon, SalonInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageTeam', $currentSalon->get());

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitación cancelada.']);

        return to_route('team.index');
    }
}
