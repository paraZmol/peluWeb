<?php

namespace App\Actions\Team;

use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use App\Exceptions\TeamException;
use App\Models\Salon;
use App\Models\SalonInvitation;
use App\Models\User;
use App\Notifications\SalonInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InviteMember
{
    /**
     * Invite someone by email. Any previous pending invitation for the same
     * email is replaced, so only the newest link works.
     *
     * @param  list<SalonPermission>  $permissions
     */
    public function handle(
        Salon $salon,
        User $inviter,
        string $email,
        SalonRole $role,
        array $permissions = [],
    ): SalonInvitation {
        $email = Str::lower(trim($email));

        $alreadyMember = $salon->members()
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->exists();

        if ($alreadyMember) {
            throw TeamException::alreadyMember();
        }

        $salon->invitations()->where('email', $email)->whereNull('accepted_at')->delete();

        $plainToken = Str::random(48);

        $invitation = new SalonInvitation;
        $invitation->salon()->associate($salon);
        $invitation->inviter()->associate($inviter);
        $invitation->email = $email;
        $invitation->role = $role;
        $invitation->permissions = collect($role === SalonRole::Owner ? [] : $permissions);
        $invitation->token_hash = SalonInvitation::hashToken($plainToken);
        $invitation->expires_at = now()->addDays(SalonInvitation::VALID_DAYS);
        $invitation->save();

        Notification::route('mail', $email)
            ->notify(new SalonInvitationNotification($invitation, $plainToken));

        return $invitation;
    }
}
