<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy
{
    /**
     * Hours, time off and services of a professional: an owner of the
     * salon edits anyone's, a professional only their own.
     */
    public function update(User $user, Staff $staff): bool
    {
        $membership = $user->membershipIn($staff->salon_id);

        if ($membership === null) {
            return false;
        }

        return $membership->isOwner() || $staff->user_id === $user->id;
    }
}
