<?php

namespace App\Actions\Accounting;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The salon gives a deposit back after a cancellation that kept it
 * refundable. Recorded once, as money going out.
 */
class RefundDeposit
{
    public function handle(Booking $booking, PaymentMethod $method, ?User $by): Booking
    {
        return DB::transaction(function () use ($booking, $method, $by): Booking {
            $locked = Booking::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($booking->id);

            if (! $locked->canRefundDeposit()) {
                throw PaymentException::nothingToRefund();
            }

            $now = CarbonImmutable::now();
            $locked->deposit_refunded_at = $now;
            $locked->save();

            Payment::record($locked, PaymentKind::Refund, $method, $locked->deposit_cents, $now, $by);

            return $locked;
        });
    }
}
