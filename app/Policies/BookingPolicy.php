<?php

namespace App\Policies;

use App\Enums\SalonPermission;
use App\Models\Booking;
use App\Models\User;

/**
 * What the salon's team may do with a booking. Clients reach their own
 * bookings through Client\BookingController, which checks ownership itself.
 */
class BookingPolicy
{
    /**
     * Owners and whoever may see everyone's agenda handle any booking;
     * a professional only the ones they attend.
     */
    public function manage(User $user, Booking $booking): bool
    {
        $membership = $user->membershipIn($booking->salon_id);

        if ($membership === null) {
            return false;
        }

        return $membership->hasPermission(SalonPermission::ViewAllSchedules)
            || $booking->staff->user_id === $user->id;
    }

    /**
     * Saying "the deposit arrived" is handling money.
     */
    public function confirmDeposit(User $user, Booking $booking): bool
    {
        return $this->manage($user, $booking)
            && ($user->membershipIn($booking->salon_id)?->hasPermission(SalonPermission::ManageCash) ?? false);
    }
}
