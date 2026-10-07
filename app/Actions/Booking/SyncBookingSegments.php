<?php

namespace App\Actions\Booking;

use App\Domain\Booking\BookingShape;
use App\Models\Booking;
use App\Models\BookingSegment;
use Carbon\CarbonImmutable;

/**
 * Writes the stretches of a booking that take its professional's time, as
 * the booking stands now (start and professional). Call it inside the
 * transaction that holds the professional's lock.
 */
class SyncBookingSegments
{
    public function handle(Booking $booking, BookingShape $shape): void
    {
        BookingSegment::query()->where('booking_id', $booking->id)->delete();

        foreach ($shape->busyAt($booking->starts_at->getTimestamp()) as $stretch) {
            $segment = new BookingSegment;
            $segment->booking_id = $booking->id;
            $segment->staff_id = $booking->staff_id;
            $segment->starts_at = CarbonImmutable::createFromTimestampUTC($stretch->start);
            $segment->ends_at = CarbonImmutable::createFromTimestampUTC($stretch->end);
            $segment->save();
        }
    }
}
