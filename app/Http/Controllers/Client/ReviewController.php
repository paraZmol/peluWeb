<?php

namespace App\Http\Controllers\Client;

use App\Actions\Booking\ClientBookings;
use App\Actions\Reviews\LeaveReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ReviewRequest;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The review of one of the person's own visits. The booking is looked up
 * through ClientBookings, so a code that is not theirs is a 404.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ClientBookings $bookings) {}

    public function store(ReviewRequest $request, string $code, LeaveReview $leave): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $leave->handle(
            $this->bookings->findOrFail($user, $code),
            $request->integer('rating'),
            $request->filled('comment') ? $request->string('comment')->trim()->toString() : null,
            $user,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gracias por tu reseña.']);

        return back();
    }

    /**
     * The person takes back what they wrote.
     */
    public function destroy(Request $request, string $code): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $booking = $this->bookings->findOrFail($user, $code);

        Review::withoutGlobalScope('salon')->where('booking_id', $booking->id)->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reseña retirada.']);

        return back();
    }
}
