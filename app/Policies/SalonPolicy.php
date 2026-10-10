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

    /**
     * Expenses, the cash register and whatever else moves the salon's money.
     */
    public function manageCash(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->hasPermission(SalonPermission::ManageCash) ?? false;
    }

    public function update(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->hasPermission(SalonPermission::ManageSalonProfile) ?? false;
    }
}
