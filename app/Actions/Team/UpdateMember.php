<?php

namespace App\Actions\Team;

use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use App\Exceptions\TeamException;
use App\Models\Salon;
use App\Models\SalonMember;
use Illuminate\Support\Facades\DB;

class UpdateMember
{
    /**
     * @param  list<SalonPermission>  $permissions
     */
    public function handle(SalonMember $member, SalonRole $role, array $permissions): SalonMember
    {
        return DB::transaction(function () use ($member, $role, $permissions): SalonMember {
            // Lock the salon so two owners cannot demote each other at once.
            $salon = Salon::query()->lockForUpdate()->findOrFail($member->salon_id);

            $losesOwnership = $member->isOwner() && $role !== SalonRole::Owner;

            if ($losesOwnership && $salon->ownersCount() <= 1) {
                throw TeamException::lastOwner();
            }

            $member->role = $role;
            $member->permissions = collect($role === SalonRole::Owner ? [] : $permissions);
            $member->save();

            return $member;
        });
    }
}
