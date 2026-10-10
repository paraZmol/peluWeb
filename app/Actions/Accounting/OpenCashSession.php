<?php

namespace App\Actions\Accounting;

use App\Exceptions\PaymentException;
use App\Models\CashSession;
use App\Models\Salon;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Opens the till with the cash it starts with. One open session per salon.
 */
class OpenCashSession
{
    public function handle(Salon $salon, int $openingCashCents, ?User $by): CashSession
    {
        return DB::transaction(function () use ($salon, $openingCashCents, $by): CashSession {
            // Lock the salon so two people cannot both open it.
            Salon::query()->lockForUpdate()->findOrFail($salon->id);

            if (CashSession::withoutGlobalScope('salon')->where('salon_id', $salon->id)->whereNull('closed_at')->exists()) {
                throw PaymentException::cashAlreadyOpen();
            }

            $session = new CashSession;
            $session->salon_id = $salon->id;
            $session->opened_at = CarbonImmutable::now();
            $session->opened_by = $by?->id;
            $session->opening_cash_cents = $openingCashCents;
            $session->save();

            return $session;
        });
    }
}
