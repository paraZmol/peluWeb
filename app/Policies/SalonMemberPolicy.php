<?php

namespace App\Policies;

use App\Models\SalonMember;
use App\Models\User;

class SalonMemberPolicy
{
    /**
     * Only an owner of the member's own salon can change or remove them.
     */
    public function update(User $user, SalonMember $member): bool
    {
        return $user->membershipIn($member->salon_id)?->isOwner() ?? false;
    }

    public function delete(User $user, SalonMember $member): bool
    {
        return $this->update($user, $member);
    }
}
