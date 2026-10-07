<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Booking\ChangeBookingStatus;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The team moves a booking along its states. `{booking}` is filtered by
 * the current salon (BelongsToSalon); BookingPolicy decides who may touch
 * it; ChangeBookingStatus enforces the state machine.
 */
class BookingStatusController extends SalonAreaController
{
    public function __construct(private readonly ChangeBookingStatus $status) {}

    public function confirm(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('confirmDeposit', $booking);
        $this->status->confirm($booking, $request->user());

        return $this->done('Reserva confirmada.');
    }

    public function start(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('manage', $booking);
        $this->status->start($booking, $request->user());

        return $this->done('Cita en atención.');
    }

    public function complete(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('manage', $booking);
        $this->status->complete($booking, $request->user());

        return $this->done('Cita completada.');
    }

    public function noShow(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('manage', $booking);
        $this->status->markNoShow($booking, $request->user());

        return $this->done('Marcada como "no vino".');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        // Cancelling on the salon's side makes a paid deposit refundable: that is handling money.
        $this->authorize($booking->deposit_paid_at === null ? 'manage' : 'confirmDeposit', $booking);
        $this->status->cancel($booking, $request->user(), byClient: false);

        return $this->done('Reserva cancelada.');
    }

    private function done(string $message): RedirectResponse
    {
        $this->toast($message);

        return back();
    }
}
