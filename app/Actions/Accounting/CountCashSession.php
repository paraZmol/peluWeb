<?php

namespace App\Actions\Accounting;

use App\Domain\Accounting\CashCount;
use App\Enums\PaymentKind;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\Payment;

/**
 * Adds up what moved while a session was open: payments in, refunds and
 * expenses out, per method. Each movement names the session it was
 * recorded in, so it counts in exactly one, or in none when the till was
 * closed.
 */
class CountCashSession
{
    public function handle(CashSession $session): CashCount
    {
        $in = [];
        $out = [];

        $payments = Payment::withoutGlobalScope('salon')
            ->where('salon_id', $session->salon_id)
            ->where('cash_session_id', $session->id)
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
            ->where('cash_session_id', $session->id)
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
