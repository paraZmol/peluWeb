<?php

namespace App\Actions\Accounting;

use App\Data\Accounting\Period;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentException;
use App\Models\Booking;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Pays one professional the commissions of a period: marks those visits
 * as settled and records the payment as an expense, so the till and the
 * reports see the money leave.
 */
class SettleCommissions
{
    public const string EXPENSE_CATEGORY = 'Sueldos y comisiones';

    /**
     * @param  int|null  $expectedCents  the total the payer saw and agreed to; refused when it is no longer that
     */
    public function handle(Staff $staff, Period $period, PaymentMethod $method, ?User $by, ?int $expectedCents = null): CommissionSettlement
    {
        return DB::transaction(function () use ($staff, $period, $method, $by, $expectedCents): CommissionSettlement {
            // Same first lock as every booking change: a visit being
            // completed right now is either in this settlement or in the next.
            Staff::withoutGlobalScope('salon')->withTrashed()->lockForUpdate()->findOrFail($staff->id);

            $pending = self::pending($staff->salon_id, $period)->where('staff_id', $staff->id)->lockForUpdate()->get(['id', 'commission_cents']);
            $total = (int) $pending->sum('commission_cents');

            if ($total === 0) {
                throw PaymentException::nothingToSettle();
            }

            if ($expectedCents !== null && $expectedCents !== $total) {
                throw PaymentException::settlementChanged();
            }

            $now = CarbonImmutable::now();

            $settlement = new CommissionSettlement;
            $settlement->salon_id = $staff->salon_id;
            $settlement->staff_id = $staff->id;
            $settlement->period_from = $period->from;
            $settlement->period_to = $period->to;
            $settlement->total_cents = $total;
            $settlement->bookings_count = $pending->count();
            $settlement->expense_id = $this->recordExpense($staff, $period, $total, $method, $now, $by)->id;
            $settlement->paid_at = $now;
            $settlement->paid_by = $by?->id;
            $settlement->save();

            Booking::withoutGlobalScope('salon')->whereKey($pending->modelKeys())->update(['commission_settlement_id' => $settlement->id]);

            return $settlement;
        }, attempts: 3);
    }

    /**
     * Completed visits of the period whose commission nobody paid yet.
     *
     * @return Builder<Booking>
     */
    public static function pending(int $salonId, Period $period): Builder
    {
        return Booking::withoutGlobalScope('salon')
            ->where('salon_id', $salonId)
            ->where('status', BookingStatus::Completed)
            ->whereNull('commission_settlement_id')
            ->where('commission_cents', '>', 0)
            ->where('starts_at', '>=', $period->startsAtUtc())
            ->where('starts_at', '<', $period->endsBeforeUtc());
    }

    private function recordExpense(Staff $staff, Period $period, int $cents, PaymentMethod $method, CarbonImmutable $now, ?User $by): Expense
    {
        $expense = new Expense;
        $expense->salon_id = $staff->salon_id;
        $expense->expense_category_id = ExpenseCategory::withoutGlobalScope('salon')
            ->where('salon_id', $staff->salon_id)->where('name', self::EXPENSE_CATEGORY)->value('id');
        $expense->amount_cents = $cents;
        $expense->method = $method;
        $expense->paid_at = $now;
        $expense->description = "Comisiones de {$staff->name}, del {$period->from->format('d/m/Y')} al {$period->to->format('d/m/Y')}";
        $expense->recorded_by = $by?->id;
        $expense->save();

        return $expense;
    }
}
