<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Enums\SalonPermission;
use App\Models\Booking;
use App\Models\SalonClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who of the team may open a client's file: it holds their history and,
 * with their authorization, health notes, so no one who has no reason to.
 */
class SalonClientPolicy
{
    /**
     * Owners, whoever runs everyone's agenda or the client list, and a
     * professional only for the people they attended or are about to.
     */
    public function view(User $user, SalonClient $client): bool
    {
        $membership = $user->membershipIn($client->salon_id);

        if ($membership === null) {
            return false;
        }

        if ($membership->hasPermission(SalonPermission::ViewAllSchedules) || $membership->hasPermission(SalonPermission::ManageVipClients)) {
            return true;
        }

        return $this->attends($user, $client);
    }

    /**
     * Allergies and reactions are health data: the owner and whoever
     * attends the person, not everyone who can look up a client.
     */
    public function viewCare(User $user, SalonClient $client): bool
    {
        $membership = $user->membershipIn($client->salon_id);

        return $membership !== null && ($membership->isOwner() || $this->attends($user, $client, onlyKept: true));
    }

    /**
     * Pictures of a person: the same few who read their health notes.
     */
    public function managePhotos(User $user, SalonClient $client): bool
    {
        return $this->viewCare($user, $client);
    }

    /**
     * Whoever may read the file may write its notes: they are the ones
     * who learn, attending, what there is to write.
     */
    public function updateNotes(User $user, SalonClient $client): bool
    {
        return $this->view($user, $client);
    }

    /**
     * The professional attended this person or has them booked.
     *
     * @param  bool  $onlyKept  leave out visits that never happened: a cancelled or lapsed booking gives no reason to read health data
     */
    private function attends(User $user, SalonClient $client, bool $onlyKept = false): bool
    {
        return Booking::withoutGlobalScope('salon')
            ->where('salon_id', $client->salon_id)
            ->where('salon_client_id', $client->id)
            ->whereRelation('staff', 'user_id', $user->id)
            ->when($onlyKept, fn (Builder $bookings) => $bookings->where(fn (Builder $kept) => $kept
                ->where('status', BookingStatus::Completed)
                ->orWhere(fn (Builder $held) => $held->holdingSlot())))
            ->exists();
    }
}
