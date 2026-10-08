<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;

/**
 * "Mi estilo": the picture a client attaches to their booking so whoever
 * attends them sees what they want. One per booking; a new one replaces it.
 */
class UpdateReferencePhoto
{
    /** Large enough to see a haircut on a phone, small enough to load fast. */
    public const int MAX_SIDE = 1200;

    public function __construct(private readonly ImageStore $images) {}

    /**
     * Only while the visit is still to come: afterwards nobody looks at it.
     */
    public function handle(Booking $booking, UploadedFile $file): void
    {
        if (! in_array($booking->effectiveStatus(), [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            throw BookingException::cannotChange($booking->effectiveStatus()->label());
        }

        $this->replace($booking, $this->images->storeFitted($file, "salons/{$booking->salon_id}/bookings", self::MAX_SIDE, self::MAX_SIDE));
    }

    public function remove(Booking $booking): void
    {
        $this->replace($booking, null);
    }

    private function replace(Booking $booking, ?string $path): void
    {
        $previous = $booking->reference_photo_path;

        // Only this column: the booking may be moved or cancelled meanwhile.
        Booking::withoutGlobalScope('salon')->whereKey($booking->id)->update(['reference_photo_path' => $path]);
        $booking->reference_photo_path = $path;
        $booking->syncOriginalAttribute('reference_photo_path');

        $this->images->delete($previous);
    }
}
