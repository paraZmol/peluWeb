<?php

namespace App\Actions\Team;

use App\Exceptions\TeamException;
use App\Models\SalonInvitation;
use App\Models\SalonMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcceptInvitation
{
    /**
     * Find a pending invitation by its plain token. The token itself is the
     * authorization, so the salon scope is bypassed on purpose: the invitee
     * is not a member of that salon yet.
     */
    public function find(string $plainToken): ?SalonInvitation
    {
        return SalonInvitation::withoutGlobalScope('salon')
            ->with('salon')
            ->where('token_hash', SalonInvitation::hashToken($plainToken))
            ->first();
    }

    public function handle(User $user, string $plainToken): SalonMember
    {
        return DB::transaction(function () use ($user, $plainToken): SalonMember {
            $invitation = $this->find($plainToken);

            if ($invitation === null || ! $invitation->isPending() || ! $invitation->salon->isWritable()) {
                throw TeamException::invitationNotValid();
            }

            if (Str::lower($user->email) !== $invitation->email) {
                throw TeamException::invitationForAnotherEmail();
            }

            $membership = SalonMember::query()
                ->where('salon_id', $invitation->salon_id)
                ->where('user_id', $user->id)
                ->first() ?? new SalonMember;

            // An invitation never downgrades an existing owner: that would
            // bypass the "a salon always has an owner" rule.
            if (! $membership->exists || ! $membership->isOwner()) {
                $membership->salon_id = $invitation->salon_id;
                $membership->user_id = $user->id;
                $membership->role = $invitation->role;
                $membership->permissions = $invitation->permissions;
                $membership->save();
            }

            $invitation->accepted_at = now();
            $invitation->save();

            return $membership;
        });
    }
}
