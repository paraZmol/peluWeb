<?php

namespace App\Http\Controllers\Client;

use App\Actions\Booking\ChangeBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClientBookingResource;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mis reservas": someone's own bookings across every salon. They are not
 * members of those salons, so the salon scope is replaced by ownership:
 * every query goes through mine(), and a code that is not theirs is a 404.
 */
class BookingController extends Controller
{
    private const int HISTORY_LIMIT = 30;

    public function index(Request $request): Response
    {
        $bookings = $this->mine($request)->orderByDesc('starts_at')->limit(self::HISTORY_LIMIT)->get();

        return Inertia::render('client/bookings/index', [
            'bookings' => ClientBookingResource::collection($bookings)->resolve(),
        ]);
    }

    public function show(Request $request, string $code): Response
    {
        return Inertia::render('client/bookings/show', [
            'booking' => new ClientBookingResource($this->find($request, $code)),
        ]);
    }

    public function cancel(Request $request, string $code, ChangeBookingStatus $status): RedirectResponse
    {
        $status->cancel($this->find($request, $code), $request->user(), byClient: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reserva cancelada.']);

        return to_route('bookings.show', $code);
    }

    private function find(Request $request, string $code): Booking
    {
        return $this->mine($request)->where('code', $code)->firstOrFail();
    }

    /**
     * Bookings whose client record belongs to the signed-in account, with
     * what the pages show, loaded without the salon scope.
     *
     * @return Builder<Booking>
     */
    private function mine(Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();
        $unscoped = fn ($query) => $query->withoutGlobalScope('salon');

        return Booking::withoutGlobalScope('salon')
            ->whereHas('client', fn (Builder $client) => $client->withoutGlobalScope('salon')->where('user_id', $user->id))
            // A deleted salon takes its bookings out of sight.
            ->whereHas('salon')
            ->with(['salon', 'services', 'client' => $unscoped, 'staff' => $unscoped]);
    }
}
