<?php

namespace App\Actions\Team;

use App\Exceptions\TeamException;
use App\Models\Salon;
use App\Models\SalonInvitation;
use App\Models\SalonMember;
use Illuminate\Support\Facades\DB;

class RemoveMember
{
    public function handle(SalonMember $member): void
    {
        DB::transaction(function () use ($member): void {
            // Lock the salon so two owners cannot remove each other at once.
            $salon = Salon::query()->lockForUpdate()->findOrFail($member->salon_id);

            if ($member->isOwner() && $salon->ownersCount() <= 1) {
                throw TeamException::lastOwner();
            }

            $member->delete();
            $this->cancelInvitationsSentBy($member);
        });
    }

    /**
     * Someone who leaves the team must not keep a working invitation
     * (possibly as owner) sent to another address of theirs.
     */
    private function cancelInvitationsSentBy(SalonMember $member): void
    {
        SalonInvitation::withoutGlobalScope('salon')
            ->where('salon_id', $member->salon_id)
            ->where('invited_by', $member->user_id)
            ->whereNull('accepted_at')
            ->delete();
    }
}
