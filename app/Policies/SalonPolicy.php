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

    /**
     * Deposit, payment details and whether the salon takes bookings: money
     * and availability, so owners only (not "edit the salon profile").
     */
    public function manageBookingRules(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->isOwner() ?? false;
    }

    public function update(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->hasPermission(SalonPermission::ManageSalonProfile) ?? false;
    }
}
