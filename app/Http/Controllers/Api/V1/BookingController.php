<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\ClientBookings;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\RescheduleBooking;
use App\Actions\Booking\ResolveSalonClient;
use App\Data\Booking\BookingMove;
use App\Data\Booking\NewBooking;
use App\Enums\BookingSource;
use App\Enums\SalonStatus;
use App\Http\Controllers\Client\BookingController as WebBookingController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\BookingDayRequest;
use App\Http\Requests\Client\MoveBookingRequest;
use App\Http\Requests\Public\StoreBookingRequest;
use App\Http\Resources\AvailabilityPayload;
use App\Http\Resources\ClientBookingResource;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The account's own bookings, for the mobile app. The same Actions and the
 * same rules as the web: only the answers are JSON. A code that is not the
 * account's is a 404.
 */
class BookingController extends Controller
{
    public function __construct(private readonly ClientBookings $bookings) {}

    public function index(Request $request): JsonResponse
    {
        $bookings = $this->bookings->query($this->user($request))
            ->orderByDesc('starts_at')
            ->limit(WebBookingController::HISTORY_LIMIT)
            ->get();

        return response()->json(['bookings' => ClientBookingResource::collection($bookings)->resolve($request)]);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        return $this->answer($request, $this->bookings->findOrFail($this->user($request), $code));
    }

    public function store(StoreBookingRequest $request, Salon $salon, CreateBooking $create, ResolveSalonClient $clients): JsonResponse
    {
        abort_if($salon->status === SalonStatus::Suspended, 404);
        $user = $this->user($request);

        // Never wrap this in a transaction: see Public\BookingController::store.
        $booking = $create->handle(new NewBooking(
            salon: $salon,
            client: $clients->forUser($salon, $user),
            selections: $request->selections(),
            staff: $request->staffIn($salon),
            startsAt: $request->startsAt(),
            source: BookingSource::Client,
            actor: $user,
            note: $request->validated('note'),
        ));

        return $this->answer($request, $this->bookings->findOrFail($user, $booking->code), 201);
    }

    public function cancel(Request $request, string $code, ChangeBookingStatus $status): JsonResponse
    {
        $user = $this->user($request);
        $status->cancel($this->bookings->findOrFail($user, $code), $user, byClient: true);

        return $this->answer($request, $this->bookings->findOrFail($user, $code));
    }

    /**
     * Times of one day the booking could move to.
     */
    public function slots(BookingDayRequest $request, string $code, RescheduleBooking $reschedule): JsonResponse
    {
        $booking = $this->bookings->findOrFail($this->user($request), $code);
        $starts = $reschedule->slots($booking, $request->localDate($booking->salon->timezone), byClient: true);

        return response()->json(['slots' => AvailabilityPayload::slots($starts, $booking->salon)]);
    }

    public function move(MoveBookingRequest $request, string $code, RescheduleBooking $reschedule): JsonResponse
    {
        $user = $this->user($request);

        // Never wrap this in a transaction: it must lock before it reads.
        $reschedule->handle(new BookingMove(
            booking: $this->bookings->findOrFail($user, $code),
            startsAt: $request->startsAt(),
            byClient: true,
            actor: $user,
        ));

        return $this->answer($request, $this->bookings->findOrFail($user, $code));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function answer(Request $request, Booking $booking, int $status = 200): JsonResponse
    {
        return response()->json(['booking' => (new ClientBookingResource($booking))->resolve($request)], $status);
    }
}
