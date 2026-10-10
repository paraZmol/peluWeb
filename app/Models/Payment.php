<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of money in a salon. Written only by the Actions that hold
 * the booking's lock (ChangeBookingStatus, RefundDeposit); never edited.
 *
 * @property int $id
 * @property int $salon_id
 * @property int|null $cash_session_id The session open when it was recorded.
 * @property int|null $booking_id
 * @property int|null $staff_id
 * @property PaymentKind $kind
 * @property PaymentMethod $method
 * @property int $amount_cents
 * @property CarbonImmutable $paid_at
 * @property int|null $recorded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Payment extends Model
{
    use BelongsToSalon;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PaymentKind::class,
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Money that changed hands for a booking. Call it under the booking's
     * lock, inside the transaction that changes the booking.
     */
    public static function record(Booking $booking, PaymentKind $kind, PaymentMethod $method, int $cents, CarbonImmutable $paidAt, ?User $by): self
    {
        $payment = new self;
        $payment->salon_id = $booking->salon_id;
        $payment->cash_session_id = CashSession::lockOpenIdFor($booking->salon_id);
        $payment->booking_id = $booking->id;
        $payment->staff_id = $booking->staff_id;
        $payment->kind = $kind;
        $payment->method = $method;
        $payment->amount_cents = $cents;
        $payment->paid_at = $paidAt;
        $payment->recorded_by = $by?->id;
        $payment->save();

        return $payment;
    }

    /**
     * Positive when it came in, negative when it went out.
     */
    public function signedCents(): int
    {
        return $this->kind->isOutgoing() ? -$this->amount_cents : $this->amount_cents;
    }
}
