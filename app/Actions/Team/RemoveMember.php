<?php

namespace App\Actions\Team;

use App\Actions\Staff\LinkStaffProfile;
use App\Exceptions\TeamException;
use App\Models\Salon;
use App\Models\SalonInvitation;
use App\Models\SalonMember;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class RemoveMember
{
    public function __construct(private readonly LinkStaffProfile $linkStaffProfile) {}

    public function handle(SalonMember $member): void
    {
        DB::transaction(function () use ($member): void {
            // The member's professional profile first, then the salon: the
            // order bookings and charges take them in (their inserts read
            // the salon's row). Detaching the profile below writes that row.
            Staff::withoutGlobalScope('salon')
                ->where('salon_id', $member->salon_id)
                ->where('user_id', $member->user_id)
                ->lockForUpdate()
                ->get(['id']);

            // Lock the salon so two owners cannot remove each other at once.
            $salon = Salon::query()->lockForUpdate()->findOrFail($member->salon_id);

            if ($member->isOwner() && $salon->ownersCount() <= 1) {
                throw TeamException::lastOwner();
            }

            $member->delete();
            $this->linkStaffProfile->unlink($member->salon_id, $member->user_id);
            $this->cancelInvitationsSentBy($member);
        }, attempts: 3);
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
