<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Accounting\RefundDeposit;
use App\Actions\Booking\ChangeBookingStatus;
use App\Actions\Booking\DropCompanion;
use App\Http\Controllers\Owner\SalonAreaController;
use App\Http\Requests\Staff\CompleteBookingRequest;
use App\Http\Requests\Staff\PaymentMethodRequest;
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

    public function confirm(PaymentMethodRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('confirmDeposit', $booking);
        $this->status->confirm($booking, $request->user(), $request->paymentMethod());

        return $this->done('Reserva confirmada.');
    }

    public function start(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('manage', $booking);
        $this->status->start($booking, $request->user());

        return $this->done('Cita en atención.');
    }

    public function complete(CompleteBookingRequest $request, Booking $booking): RedirectResponse
    {
        $checkout = $request->checkout();
        // Taking money off the price is deciding over the salon's income.
        $this->authorize($checkout !== null && $checkout->discountCents > 0 ? 'confirmDeposit' : 'manage', $booking);
        $this->status->complete($booking, $request->user(), $checkout);

        return $this->done('Cita completada y cobrada.');
    }

    public function dropCompanion(Request $request, Booking $booking, DropCompanion $drop): RedirectResponse
    {
        $this->authorize('manage', $booking);
        // Who came: the one who booked, or the one coming along.
        $came = $request->validate(['came' => ['required', 'in:booker,companion']])['came'];
        $drop->handle($booking, $came === 'companion', $request->user());

        return $this->done('Listo: se cobra solo a quien vino, a precio normal.');
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

    public function refundDeposit(PaymentMethodRequest $request, Booking $booking, RefundDeposit $refund): RedirectResponse
    {
        $this->authorize('confirmDeposit', $booking);
        $refund->handle($booking, $request->paymentMethod(), $request->user());

        return $this->done('Seña devuelta.');
    }

    private function done(string $message): RedirectResponse
    {
        $this->toast($message);

        return back();
    }
}
