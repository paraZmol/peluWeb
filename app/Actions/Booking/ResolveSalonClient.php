<?php

namespace App\Actions\Booking;

use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\User;
use App\Support\PersonName;

/**
 * Finds the record a salon keeps of a person, or prepares a new one.
 * Names the salon explicitly: a client booking online is not a member of it.
 *
 * A new record is returned unsaved: CreateBooking stores it in the same
 * transaction as the booking, so a refused booking leaves no empty record
 * and nothing has to be deleted afterwards (deleting it once took another
 * request's booking with it).
 */
class ResolveSalonClient
{
    /**
     * The client record of an account in this salon. Never VIP when new:
     * only the salon grants that.
     */
    public function forUser(Salon $salon, User $user): SalonClient
    {
        return $this->find($salon, $user)
            ?? $this->prepare($salon, PersonName::clean($user->name, SalonClient::NAME_MAX_LENGTH, 'Cliente'), $user->phone, $user);
    }

    /**
     * Someone the team books for by name and phone. A record without an
     * account that already has that phone is the same person. Records of
     * accounts are never matched by phone: the phone of an account is
     * whatever its owner typed, and matching it would hand them the
     * bookings of whoever really has that number.
     */
    public function walkIn(Salon $salon, string $name, ?string $phone): SalonClient
    {
        $known = $phone === null ? null : SalonClient::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->whereNull('user_id')
            ->where('phone', $phone)
            ->orderBy('id')
            ->first();

        return $known ?? $this->prepare($salon, PersonName::clean($name, SalonClient::NAME_MAX_LENGTH, 'Cliente'), $phone);
    }

    public function find(Salon $salon, User $user): ?SalonClient
    {
        return SalonClient::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function prepare(Salon $salon, string $name, ?string $phone, ?User $user = null): SalonClient
    {
        $client = new SalonClient;
        $client->salon_id = $salon->id;
        $client->user_id = $user?->id;
        $client->name = $name;
        $client->phone = $phone;

        return $client;
    }
}
