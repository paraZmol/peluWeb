<?php

namespace App\Policies;

use App\Enums\SalonPermission;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\Staff;
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
     * Booking for a client (phone, in person, walk-in): whoever runs
     * everyone's agenda, or a professional for themselves.
     */
    public function create(User $user, Salon $salon): bool
    {
        $membership = $user->membershipIn($salon);

        if ($membership === null) {
            return false;
        }

        return $membership->hasPermission(SalonPermission::ViewAllSchedules)
            || Staff::withoutGlobalScope('salon')
                ->where('salon_id', $salon->id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->exists();
    }

    /**
     * Handing a booking to a colleague is deciding over someone else's
     * agenda.
     */
    public function reassign(User $user, Booking $booking): bool
    {
        return $user->membershipIn($booking->salon_id)?->hasPermission(SalonPermission::ViewAllSchedules) ?? false;
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
