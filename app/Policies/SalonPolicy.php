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

    /**
     * Promotions change what the salon charges: owners only.
     */
    public function managePromotions(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->isOwner() ?? false;
    }

    public function manageServices(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->hasPermission(SalonPermission::ManageServices) ?? false;
    }

    /**
     * The client list: who marks VIPs, and who sees everyone's agenda
     * (they already open each client's file from a booking).
     */
    public function viewClients(User $user, Salon $salon): bool
    {
        $membership = $user->membershipIn($salon);

        return $membership !== null
            && ($membership->hasPermission(SalonPermission::ManageVipClients) || $membership->hasPermission(SalonPermission::ViewAllSchedules));
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

    public function viewReports(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->hasPermission(SalonPermission::ViewReports) ?? false;
    }

    /**
     * "Mi plan": what the salon pays the platform. Owners only.
     */
    public function viewSubscription(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->isOwner() ?? false;
    }

    public function update(User $user, Salon $salon): bool
    {
        return $user->membershipIn($salon)?->hasPermission(SalonPermission::ManageSalonProfile) ?? false;
    }
}
