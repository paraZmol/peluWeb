<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Reviews\ModerateReview;
use App\Actions\Reviews\SalonReviews;
use App\Enums\ReviewMode;
use App\Http\Requests\Owner\ReviewModerationRequest;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The salon's reviews: reading them, choosing who else reads them,
 * answering and reporting. For whoever edits the salon's profile.
 * `{review}` is filtered by the current salon (BelongsToSalon).
 */
class ReviewController extends SalonAreaController
{
    public function index(SalonReviews $reviews): Response
    {
        $salon = $this->salon('update');

        return Inertia::render('owner/reviews/index', [
            'mode' => $salon->review_mode->value,
            'modes' => array_map(fn (ReviewMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
                'hint' => $mode->hint(),
            ], ReviewMode::cases()),
            'reviews' => $reviews->forTeam($salon),
            'limits' => ['reply' => Review::COMMENT_MAX_LENGTH, 'report' => Review::REPORT_MAX_LENGTH],
        ]);
    }

    public function updateMode(Request $request): RedirectResponse
    {
        $salon = $this->salon('update');
        $validated = $request->validate(['mode' => ['required', 'string', Rule::enum(ReviewMode::class)]]);

        $salon->review_mode = ReviewMode::from($validated['mode']);
        $salon->save();
        $this->toast("Reseñas: {$salon->review_mode->label()}.");

        return back();
    }

    public function reply(ReviewModerationRequest $request, Review $review, ModerateReview $moderate): RedirectResponse
    {
        $this->salon('update');

        $moderate->reply($review, $request->text('reply'), $request->user());
        $this->toast($review->reply === null ? 'Respuesta retirada.' : 'Respuesta publicada.');

        return back();
    }

    public function report(ReviewModerationRequest $request, Review $review, ModerateReview $moderate): RedirectResponse
    {
        $this->salon('update');

        $moderate->report($review, (string) $request->text('reason'), $request->user());
        $this->toast('Reseña reportada. La revisará el equipo de peluWeb.');

        return back();
    }
}
