<?php

namespace App\Http\Controllers\Owner;

use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use App\Http\Controllers\Controller;
use App\Models\SalonInvitation;
use App\Models\SalonMember;
use App\Models\Staff;
use App\Support\CurrentSalon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request, CurrentSalon $currentSalon): Response
    {
        $salon = $currentSalon->get();
        $this->authorize('manageTeam', $salon);

        return Inertia::render('owner/team/index', [
            'members' => $salon?->members()->with('user')->oldest()->get()
                ->map(fn (SalonMember $member) => [
                    'id' => $member->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'role' => $member->role->value,
                    'permissions' => $member->permissions?->map(fn (SalonPermission $p) => $p->value)->values() ?? [],
                    'isYou' => $member->user_id === $request->user()?->id,
                ]),
            'invitations' => SalonInvitation::query()->pending()->latest()->get()
                ->map(fn (SalonInvitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'expiresAt' => $invitation->expires_at->toIso8601String(),
                ]),
            // Professionals without an account: an invitation can hand one over.
            'unlinkedStaff' => $salon?->staff()->whereNull('user_id')->get()
                ->map(fn (Staff $staff) => ['id' => $staff->id, 'name' => $staff->name]),
            'roles' => collect(SalonRole::cases())
                ->map(fn (SalonRole $role) => ['value' => $role->value, 'label' => $role->label()]),
            'permissionOptions' => collect(SalonPermission::cases())
                ->map(fn (SalonPermission $p) => ['value' => $p->value, 'label' => $p->label()]),
        ]);
    }
}
