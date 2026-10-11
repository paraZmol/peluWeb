<?php

namespace App\Actions\Reviews;

use App\Enums\BookingStatus;
use App\Exceptions\ReviewException;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Salon;
use App\Models\User;
use App\Support\PersonName;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A client says how a completed visit went: once per visit, signed with
 * their first name only. The caller made sure the booking is theirs.
 */
class LeaveReview
{
    public function handle(Booking $booking, int $rating, ?string $comment, User $author): Review
    {
        $salon = Salon::query()->findOrFail($booking->salon_id);

        if ($booking->status !== BookingStatus::Completed) {
            throw ReviewException::visitNotCompleted();
        }

        if (! $salon->review_mode->collects()) {
            throw ReviewException::notCollected();
        }

        if ($booking->starts_at->lessThan(CarbonImmutable::now()->subDays(Review::WINDOW_DAYS))) {
            throw ReviewException::tooLate(Review::WINDOW_DAYS);
        }

        $review = new Review;
        $review->salon_id = $booking->salon_id;
        $review->booking_id = $booking->id;
        $review->salon_client_id = $booking->salon_client_id;
        $review->staff_id = $booking->staff_id;
        $review->rating = $rating;
        $review->comment = $comment;
        $review->author_name = self::firstName($author->name);

        try {
            $review->save();
        } catch (UniqueConstraintViolationException) {
            throw ReviewException::alreadyReviewed();
        }

        return $review;
    }

    /**
     * "María Fernanda Quispe" → "María": enough to sound like a person,
     * not enough to find them.
     */
    public static function firstName(string $name): string
    {
        $clean = PersonName::clean($name, 40, Review::ANONYMOUS);

        return explode(' ', $clean)[0] ?: Review::ANONYMOUS;
    }
}
