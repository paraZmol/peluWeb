<?php

namespace App\Actions\Billing;

use App\Data\Billing\NewSubscriptionPayment;
use App\Enums\SalonStatus;
use App\Enums\SubscriptionState;
use App\Exceptions\BillingException;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\SubscriptionPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records a payment the platform received and extends what the salon has
 * paid for (PLAN 7.2). The covered days follow on from the last covered
 * day, so days of grace are not free; a salon already read only starts
 * from today, since it could not use those days.
 */
class RecordSubscriptionPayment
{
    public function handle(NewSubscriptionPayment $data): SubscriptionPayment
    {
        if (! in_array($data->months, [1, Plan::YEAR_MONTHS], true)) {
            throw BillingException::invalidMonths();
        }

        return DB::transaction(function () use ($data): SubscriptionPayment {
            /** @var Salon $salon */
            $salon = Salon::query()->lockForUpdate()->findOrFail($data->salon->id);
            $this->ensureFits($salon, $data->plan);

            $standing = $salon->standing();
            $today = $salon->localNow()->startOfDay();
            $from = $standing->state === SubscriptionState::ReadOnly || $standing->coveredUntil === null
                ? $today
                : $standing->coveredUntil->addDay();

            // The founder price is kept while the salon keeps paying.
            if ($standing->state === SubscriptionState::ReadOnly) {
                $salon->founder_since = null;
            }

            // The places are counted across salons: two payments at once take turns.
            Plan::query()->lockForUpdate()->get();

            if ($salon->founder_since === null && $data->plan->founder_price_cents !== null && self::founderOfferIsOpen($today)) {
                $salon->founder_since = now();
            }

            $payment = new SubscriptionPayment;
            $payment->salon_id = $salon->id;
            $payment->plan_id = $data->plan->id;
            $payment->months = $data->months;
            $payment->amount_cents = $data->amountCents;
            $payment->method = $data->method;
            $payment->covers_from = $from;
            $payment->covers_until = $from->addMonthsNoOverflow($data->months)->subDay();
            $payment->is_founder = $salon->founder_since !== null;
            $payment->recorded_by = $data->recordedBy->id;
            $payment->note = $data->note;
            $payment->save();

            $salon->plan_id = $data->plan->id;
            $salon->paid_until = $payment->covers_until;
            $salon->status = $salon->status === SalonStatus::Suspended ? SalonStatus::Suspended : SalonStatus::Active;
            $salon->save();

            return $payment;
        });
    }

    /**
     * What this salon is expected to pay: its monthly price, or ten months
     * for a year.
     */
    public static function expectedCents(Salon $salon, Plan $plan, int $months): int
    {
        $keepsIt = $salon->founder_since !== null && $salon->standing()->state !== SubscriptionState::ReadOnly;
        $getsIt = $keepsIt || self::founderOfferIsOpen($salon->localNow()->startOfDay());
        $monthly = $getsIt && $plan->founder_price_cents !== null ? $plan->founder_price_cents : $plan->price_cents;

        return $monthly * ($months === Plan::YEAR_MONTHS ? Plan::YEAR_PAID_MONTHS : $months);
    }

    /**
     * A smaller plan is refused while more people have an agenda than it
     * allows: the owner decides who stops having one, never the system.
     */
    private function ensureFits(Salon $salon, Plan $plan): void
    {
        $withAgenda = Staff::withoutGlobalScope('salon')->where('salon_id', $salon->id)->where('is_active', true)->count();

        if ($withAgenda > $plan->max_staff) {
            throw BillingException::tooManyWithAgenda($withAgenda, $plan->name, $plan->max_staff);
        }
    }

    /**
     * Whether a salon paying today still gets the founder price: places
     * left and the offer's last day not passed.
     */
    public static function founderOfferIsOpen(CarbonImmutable $today): bool
    {
        $endsOn = config('billing.founder.ends_on');

        if (is_string($endsOn) && $endsOn !== '' && $today->gt(CarbonImmutable::parse($endsOn)->startOfDay())) {
            return false;
        }

        return Salon::query()->whereNotNull('founder_since')->count() < (int) config('billing.founder.slots');
    }
}
