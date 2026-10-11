<?php

namespace App\Actions\Reviews;

use App\Exceptions\ReviewException;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * What a salon and the platform do with a review once it exists. The
 * salon answers and may ask the platform to look at one; only the
 * platform hides it. Nobody edits what the client wrote.
 */
class ModerateReview
{
    /**
     * @param  string|null  $reply  null takes the answer back
     */
    public function reply(Review $review, ?string $reply, ?User $by): Review
    {
        $review->reply = $reply;
        $review->replied_at = $reply === null ? null : CarbonImmutable::now();
        $review->replied_by = $reply === null ? null : $by?->id;
        $review->save();

        return $review;
    }

    public function report(Review $review, string $reason, ?User $by): Review
    {
        if ($review->reported_at !== null && $review->report_resolved_at === null) {
            throw ReviewException::alreadyReported();
        }

        $review->reported_at = CarbonImmutable::now();
        $review->report_reason = $reason;
        $review->reported_by = $by?->id;
        $review->report_resolved_at = null;
        $review->save();

        return $review;
    }

    /**
     * The platform's answer to a report: hide the review, or leave it up.
     */
    public function resolve(Review $review, bool $hide): Review
    {
        $now = CarbonImmutable::now();
        $review->report_resolved_at = $now;
        $review->hidden_at = $hide ? $now : null;
        $review->save();

        return $review;
    }
}
