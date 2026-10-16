<?php

namespace App\Actions\Staff;

use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;

class LinkStaffProfile
{
    public function __construct(private readonly SaveStaff $saveStaff) {}

    /**
     * Give a team member their professional profile: keep the one they
     * have, take over the unlinked one the owner chose, or create it.
     */
    public function handle(Salon $salon, User $user, ?int $preferredStaffId = null): Staff
    {
        $profiles = Staff::withoutGlobalScope('salon')->where('salon_id', $salon->id);

        $own = (clone $profiles)->where('user_id', $user->id)->first();

        if ($own !== null) {
            return $own;
        }

        $preferred = $preferredStaffId === null
            ? null
            : (clone $profiles)->whereNull('user_id')->find($preferredStaffId);

        // Joining never fails for the plan: with no room left, a new profile
        // starts without an agenda and the owner decides who keeps one.
        $attributes = ['name' => $preferred->name ?? Staff::nameFrom($user->name)];

        if ($preferred === null && ! $salon->hasRoomForStaff()) {
            $attributes['is_active'] = false;
        }

        return $this->saveStaff->handle($salon, $preferred, $attributes, $user->id);
    }

    /**
     * The profile stays (with its hours and, later, its history); only the
     * account is detached.
     */
    public function unlink(int $salonId, int $userId): void
    {
        Staff::withoutGlobalScope('salon')
            ->where('salon_id', $salonId)
            ->where('user_id', $userId)
            ->update(['user_id' => null]);
    }
}
