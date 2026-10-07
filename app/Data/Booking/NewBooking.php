<?php

namespace App\Data\Booking;

use App\Enums\BookingSource;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Everything CreateBooking needs. What the client may book (VIP hours,
 * notice, deposit) is derived from $client and $source, never passed in.
 */
final readonly class NewBooking
{
    /**
     * @param  list<array{service_id: int, variant_id: int|null}>  $selections  in the order they will be done
     * @param  SalonClient  $client  may be new and unsaved: it is stored together with the booking
     * @param  Staff|null  $staff  null = whoever is free
     */
    public function __construct(
        public Salon $salon,
        public SalonClient $client,
        public array $selections,
        public ?Staff $staff,
        public CarbonImmutable $startsAt,
        public BookingSource $source,
        public ?User $actor = null,
        public ?string $note = null,
    ) {}
}
