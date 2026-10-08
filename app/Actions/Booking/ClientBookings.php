<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Someone's own bookings across every salon ("Mis reservas", on the web and
 * in the API). They are not members of those salons, so the salon scope is
 * replaced by ownership: every query starts here, and a code that is not
 * theirs simply does not exist.
 */
class ClientBookings
{
    /**
     * Bookings whose client record belongs to the account, with what the
     * pages show, loaded without the salon scope.
     *
     * @return Builder<Booking>
     */
    public function query(User $user): Builder
    {
        $unscoped = fn ($query) => $query->withoutGlobalScope('salon');

        return Booking::withoutGlobalScope('salon')
            ->whereHas('client', fn (Builder $client) => $client->withoutGlobalScope('salon')->where('user_id', $user->id))
            // A deleted salon takes its bookings out of sight.
            ->whereHas('salon')
            ->with(['salon', 'services', 'client' => $unscoped, 'staff' => $unscoped]);
    }

    public function findOrFail(User $user, string $code): Booking
    {
        return $this->query($user)->where('code', $code)->firstOrFail();
    }
}
