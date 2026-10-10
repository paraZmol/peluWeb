<?php

namespace App\Actions\Accounting;

use App\Data\Accounting\Period;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Salon;
use Illuminate\Database\Eloquent\Builder;

/**
 * The queries every figure of a report starts from: one salon, one period.
 * A visit counts on the day it took place; an expense and a payment, on
 * the day the money changed hands.
 */
final readonly class ReportScope
{
    public function __construct(public Salon $salon, public Period $period) {}

    /**
     * @return Builder<Booking>
     */
    public function bookings(): Builder
    {
        return Booking::withoutGlobalScope('salon')
            ->where('bookings.salon_id', $this->salon->id)
            ->where('bookings.starts_at', '>=', $this->period->startsAtUtc())
            ->where('bookings.starts_at', '<', $this->period->endsBeforeUtc());
    }

    /**
     * @return Builder<Booking>
     */
    public function completed(): Builder
    {
        return $this->bookings()->where('bookings.status', BookingStatus::Completed);
    }

    /**
     * Paid deposits of visits that did not happen and are not owed back.
     *
     * @return Builder<Booking>
     */
    public function keptDeposits(): Builder
    {
        return $this->bookings()
            ->whereIn('bookings.status', [BookingStatus::Cancelled, BookingStatus::NoShow])
            ->whereNotNull('bookings.deposit_paid_at')
            ->where('bookings.deposit_refundable', false);
    }

    /**
     * @return Builder<Expense>
     */
    public function expenses(): Builder
    {
        return Expense::withoutGlobalScope('salon')
            ->where('expenses.salon_id', $this->salon->id)
            ->where('expenses.paid_at', '>=', $this->period->startsAtUtc())
            ->where('expenses.paid_at', '<', $this->period->endsBeforeUtc());
    }

    /**
     * @return Builder<Payment>
     */
    public function payments(): Builder
    {
        return Payment::withoutGlobalScope('salon')
            ->where('salon_id', $this->salon->id)
            ->where('paid_at', '>=', $this->period->startsAtUtc())
            ->where('paid_at', '<', $this->period->endsBeforeUtc());
    }
}
