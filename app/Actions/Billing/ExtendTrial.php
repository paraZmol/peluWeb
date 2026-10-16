<?php

namespace App\Actions\Billing;

use App\Exceptions\BillingException;
use App\Models\Salon;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\DB;

/**
 * The one extension of the trial the platform admin may give by hand to a
 * salon still deciding (PLAN 7.2). It counts from today if the trial
 * already ended, so a salon in read only gets its full extension.
 */
class ExtendTrial
{
    public function handle(Salon $salon): Salon
    {
        return DB::transaction(function () use ($salon): Salon {
            /** @var Salon $locked */
            $locked = Salon::query()->lockForUpdate()->findOrFail($salon->id);

            if ($locked->trial_extended_at !== null) {
                throw BillingException::trialAlreadyExtended();
            }

            if (SubscriptionPayment::withoutGlobalScope('salon')->where('salon_id', $locked->id)->exists()) {
                throw BillingException::alreadyPaying();
            }

            $from = $locked->trial_ends_at === null ? now() : $locked->trial_ends_at->max(now());
            $locked->trial_ends_at = $from->addDays((int) config('billing.trial_extension_days'));
            $locked->trial_extended_at = now();
            $locked->save();

            return $locked;
        });
    }
}
