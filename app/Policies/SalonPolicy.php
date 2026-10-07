<?php

namespace App\Policies;

use App\Enums\SalonPermission;
use App\Models\Salon;
use App\Models\User;

class SalonPolicy
{
    public function manageTeam(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->isOwner() ?? false;
    }

    public function manageServices(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->hasPermission(SalonPermission::ManageServices) ?? false;
    }

    public function manageVipClients(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->hasPermission(SalonPermission::ManageVipClients) ?? false;
    }

    public function update(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->hasPermission(SalonPermission::ManageSalonProfile) ?? false;
    }
}
