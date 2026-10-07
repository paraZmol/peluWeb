<?php

namespace App\Data\Booking;

use App\Models\Booking;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A request to move a booking to another time. Services, prices and
 * durations stay as they were booked.
 */
final readonly class BookingMove
{
    /**
     * @param  bool  $byClient  clients keep their professional, need the salon's notice and book only their own hours
     * @param  Staff|null  $staff  null = stays with the same professional
     */
    public function __construct(
        public Booking $booking,
        public CarbonImmutable $startsAt,
        public bool $byClient,
        public ?User $actor = null,
        public ?Staff $staff = null,
    ) {}
}
