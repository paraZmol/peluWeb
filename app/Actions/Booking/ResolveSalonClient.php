<?php

namespace App\Actions\Booking;

use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Finds or creates the record a salon keeps of a person. Names the salon
 * explicitly: a client booking online is not a member of it.
 */
class ResolveSalonClient
{
    /**
     * The client record of an account in this salon. Never VIP when new:
     * only the salon grants that.
     */
    public function forUser(Salon $salon, User $user): SalonClient
    {
        $existing = $this->find($salon, $user);

        if ($existing !== null) {
            return $existing;
        }

        $client = new SalonClient;
        $client->salon_id = $salon->id;
        $client->user_id = $user->id;
        $client->name = PersonName::clean($user->name, SalonClient::NAME_MAX_LENGTH, 'Cliente');
        $client->phone = $user->phone;

        try {
            $client->save();
        } catch (UniqueConstraintViolationException) {
            // Two first bookings at once: the other request created it.
            return $this->find($salon, $user) ?? throw new \LogicException('Salon client vanished after a unique violation.');
        }

        return $client;
    }

    /**
     * Someone the team books for, with or without an account. Whoever
     * already has that phone in the salon is the same person; otherwise a
     * new record without an account is created.
     */
    public function walkIn(Salon $salon, string $name, ?string $phone): SalonClient
    {
        $known = $phone === null ? null : SalonClient::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('phone', $phone)
            ->orderBy('id')
            ->first();

        if ($known !== null) {
            return $known;
        }

        $client = new SalonClient;
        $client->salon_id = $salon->id;
        $client->name = PersonName::clean($name, SalonClient::NAME_MAX_LENGTH, 'Cliente');
        $client->phone = $phone;
        $client->save();

        return $client;
    }

    public function find(Salon $salon, User $user): ?SalonClient
    {
        return SalonClient::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('user_id', $user->id)
            ->first();
    }
}
