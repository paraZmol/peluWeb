<?php

namespace App\Actions\Accounting;

use App\Exceptions\PaymentException;
use App\Models\CashSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Closes the till: freezes what should be in the drawer at this instant
 * next to what was actually counted.
 */
class CloseCashSession
{
    public function __construct(private readonly CountCashSession $count) {}

    public function handle(CashSession $session, int $countedCashCents, ?string $note, ?User $by): CashSession
    {
        return DB::transaction(function () use ($session, $countedCashCents, $note, $by): CashSession {
            // The lock every movement takes before naming its session: the
            // ones under way finish first, the rest find the till closed.
            CashSession::lockOpenIdFor($session->salon_id);
            $locked = CashSession::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isOpen()) {
                throw PaymentException::cashAlreadyClosed();
            }

            $locked->closed_at = CarbonImmutable::now();
            $locked->expected_cash_cents = $this->count->handle($locked)->expectedCashCents();
            $locked->counted_cash_cents = $countedCashCents;
            $locked->note = $note;
            $locked->closed_by = $by?->id;
            $locked->save();

            return $locked;
        });
    }
}
