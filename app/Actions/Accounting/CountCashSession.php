<?php

namespace App\Actions\Accounting;

use App\Domain\Accounting\CashCount;
use App\Enums\PaymentKind;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\Payment;

/**
 * Adds up what moved from a session's opening up to (not including) the
 * instant it closed, or everything since it opened while it is still open:
 * payments in, refunds and expenses out, per method. Half-open on purpose,
 * so a movement never counts in two consecutive sessions.
 */
class CountCashSession
{
    public function handle(CashSession $session): CashCount
    {
        $in = [];
        $out = [];

        $payments = Payment::withoutGlobalScope('salon')
            ->where('salon_id', $session->salon_id)
            ->where('paid_at', '>=', $session->opened_at)
            ->when($session->closed_at !== null, fn ($query) => $query->where('paid_at', '<', $session->closed_at))
            ->selectRaw('kind, method, SUM(amount_cents) as total')
            ->groupBy('kind', 'method')
            ->toBase()
            ->get();

        foreach ($payments as $row) {
            if (PaymentKind::from($row->kind)->isOutgoing()) {
                $out[$row->method] = ($out[$row->method] ?? 0) + (int) $row->total;
            } else {
                $in[$row->method] = ($in[$row->method] ?? 0) + (int) $row->total;
            }
        }

        $expenses = Expense::withoutGlobalScope('salon')
            ->where('salon_id', $session->salon_id)
            ->where('paid_at', '>=', $session->opened_at)
            ->when($session->closed_at !== null, fn ($query) => $query->where('paid_at', '<', $session->closed_at))
            ->selectRaw('method, SUM(amount_cents) as total')
            ->groupBy('method')
            ->toBase()
            ->get();

        foreach ($expenses as $row) {
            $out[$row->method] = ($out[$row->method] ?? 0) + (int) $row->total;
        }

        return new CashCount($session->opening_cash_cents, $in, $out);
    }
}
