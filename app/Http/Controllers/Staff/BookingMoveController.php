<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Booking\RescheduleBooking;
use App\Data\Booking\BookingMove;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Http\Requests\Client\BookingDayRequest;
use App\Http\Requests\Staff\MoveBookingRequest;
use App\Http\Resources\AvailabilityPayload;
use App\Models\Booking;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The team moves a booking to another time or to a colleague. `{booking}`
 * is filtered by the current salon (BelongsToSalon); BookingPolicy decides
 * who may move it and who may hand it to someone else.
 */
class BookingMoveController extends SalonAreaController
{
    public function __construct(private readonly RescheduleBooking $reschedule) {}

    /**
     * Times of one day the booking could move to.
     */
    public function slots(BookingDayRequest $request, Booking $booking): JsonResponse
    {
        $this->authorize('manage', $booking);
        $salon = $this->currentSalon();

        $starts = $this->reschedule->slots(
            $booking, $request->localDate($salon->timezone), byClient: false, staff: $this->newStaff($request, $booking),
        );

        return response()->json(['slots' => AvailabilityPayload::slots($starts, $salon)]);
    }

    public function update(MoveBookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('manage', $booking);
        $salon = $this->currentSalon();

        // Never wrap this in a transaction: it must lock before it reads.
        $moved = $this->reschedule->handle(new BookingMove(
            booking: $booking,
            startsAt: $request->startsAtIn($salon->timezone),
            byClient: false,
            actor: $request->user(),
            staff: $this->newStaff($request, $booking),
        ));

        $this->toast('Cita movida. Avísale a tu cliente.');

        // Dropped on the grid: stay on the view it was dropped in.
        if ($request->boolean('stay')) {
            return back();
        }

        return to_route('agenda.index', ['fecha' => $moved->starts_at->setTimezone($salon->timezone)->toDateString()]);
    }

    /**
     * The colleague the booking is handed to; null when it stays put.
     */
    private function newStaff(Request $request, Booking $booking): ?Staff
    {
        $id = $request->integer('staff');

        if ($id === 0 || $id === $booking->staff_id) {
            return null;
        }

        $this->authorize('reassign', $booking);

        return Staff::query()->where('is_active', true)->find($id)
            ?? throw ValidationException::withMessages(['staff' => 'Esa persona ya no está disponible. Elige otra.']);
    }
}
