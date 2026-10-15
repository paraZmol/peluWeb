<?php

namespace App\Actions\Reviews;

use App\Enums\ReviewMode;
use App\Models\Review;
use App\Models\Salon;
use Illuminate\Database\Eloquent\Builder;

/**
 * A salon's reviews as they are shown: the average, how many, and the
 * latest ones. The public gets them only while the salon keeps them
 * public, and never the ones the platform hid.
 */
class SalonReviews
{
    public const int PUBLIC_LIMIT = 20;

    public const int OWNER_LIMIT = 100;

    /**
     * For the salon's public page. Null unless the salon shows them.
     *
     * @return array{average: float|null, count: int, items: array<int, array<string, mixed>>}|null
     */
    public function forPublic(Salon $salon): ?array
    {
        if ($salon->review_mode !== ReviewMode::Public) {
            return null;
        }

        $visible = fn (): Builder => $this->query($salon)->publishable();

        return [
            ...$this->summary($visible()),
            'items' => $visible()->orderByDesc('id')->limit(self::PUBLIC_LIMIT)->get()
                ->map(fn (Review $review): array => $this->item($review, $salon))->all(),
        ];
    }

    /**
     * For the salon's own team: every review, with what only they see.
     *
     * @return array{average: float|null, count: int, items: array<int, array<string, mixed>>}
     */
    public function forTeam(Salon $salon): array
    {
        return [
            ...$this->summary($this->query($salon)->visible()),
            'items' => $this->query($salon)->standing()->with('staff')->orderByDesc('id')->limit(self::OWNER_LIMIT)->get()
                ->map(fn (Review $review): array => [
                    ...$this->item($review, $salon),
                    'staffName' => $review->staff->name,
                    'isReported' => $review->reported_at !== null && $review->report_resolved_at === null,
                    'isHidden' => $review->hidden_at !== null,
                    // Written as private: stays out of the public page whatever the mode.
                    'isPublishable' => $review->is_publishable,
                    // Looked at and left up: reporting it again is still possible.
                    'wasKept' => $review->report_resolved_at !== null && $review->hidden_at === null,
                ])->all(),
        ];
    }

    /**
     * @return Builder<Review>
     */
    private function query(Salon $salon): Builder
    {
        return Review::withoutGlobalScope('salon')->where('salon_id', $salon->id);
    }

    /**
     * @param  Builder<Review>  $reviews
     * @return array{average: float|null, count: int}
     */
    private function summary(Builder $reviews): array
    {
        $totals = $reviews->selectRaw('COUNT(*) as reviews, AVG(rating) as average')->toBase()->first();
        $count = (int) ($totals->reviews ?? 0);

        return [
            'average' => $count === 0 ? null : round((float) $totals->average, 1),
            'count' => $count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(Review $review, Salon $salon): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
            'author' => $review->author_name,
            'date' => $review->created_at?->setTimezone($salon->timezone)->format('Y-m-d'),
            'reply' => $review->reply,
            'repliedOn' => $review->replied_at?->setTimezone($salon->timezone)->format('Y-m-d'),
        ];
    }
}
