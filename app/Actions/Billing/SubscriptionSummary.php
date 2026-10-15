<?php

namespace App\Actions\Billing;

use App\Models\Plan;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\SubscriptionPayment;

/**
 * What "Mi plan" shows the owner: where the salon stands, what it pays,
 * the plans it could move to at its own prices, how to pay and what it
 * already paid (PLAN 7.2 and 7.3).
 */
class SubscriptionSummary
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Salon $salon): array
    {
        $standing = $salon->standing();
        $plan = $salon->plan ?? Plan::trial();
        $payments = SubscriptionPayment::query()->with('plan')->latest('covers_from')->get();
        $graceDays = (int) config('billing.grace_days');

        return [
            'state' => $standing->state->value,
            'stateLabel' => $standing->state->label(),
            'coveredUntil' => $standing->coveredUntil?->toDateString(),
            'daysLeft' => $standing->daysLeft,
            'graceEndsOn' => $standing->coveredUntil?->addDays($graceDays)->toDateString(),
            'plan' => ['code' => $plan->code, 'name' => $plan->name, 'maxStaff' => $plan->max_staff],
            'withAgenda' => Staff::query()->where('is_active', true)->count(),
            'isFounder' => $salon->founder_since !== null,
            'plans' => $this->plans($salon, $plan),
            // A year is offered from the second payment on: at sign up it scares (PLAN §11).
            'offersYear' => $payments->isNotEmpty(),
            'payTo' => [
                'yape' => config('billing.pay_to.yape'),
                'name' => config('billing.pay_to.name'),
            ],
            'payments' => $payments->map(fn (SubscriptionPayment $payment): array => [
                'id' => $payment->id,
                'paidOn' => $payment->created_at?->setTimezone($salon->timezone)->toDateString(),
                'planName' => $payment->plan->name,
                'months' => $payment->months,
                'amountCents' => $payment->amount_cents,
                'method' => $payment->method->label(),
                'coversFrom' => $payment->covers_from->toDateString(),
                'coversUntil' => $payment->covers_until->toDateString(),
            ])->values()->all(),
        ];
    }

    /**
     * The public plans plus the salon's own, at what this salon would pay.
     *
     * @return array<int, array{code: string, name: string, maxStaff: int, monthlyCents: int, yearCents: int, normalCents: int, isCurrent: bool}>
     */
    private function plans(Salon $salon, Plan $current): array
    {
        return Plan::query()
            ->where(fn ($query) => $query->where('is_public', true)->orWhere('id', $current->id))
            ->orderBy('position')
            ->get()
            ->map(fn (Plan $plan): array => [
                'code' => $plan->code,
                'name' => $plan->name,
                'maxStaff' => $plan->max_staff,
                'monthlyCents' => RecordSubscriptionPayment::expectedCents($salon, $plan, 1),
                'yearCents' => RecordSubscriptionPayment::expectedCents($salon, $plan, Plan::YEAR_MONTHS),
                'normalCents' => $plan->price_cents,
                'isCurrent' => $plan->id === $current->id,
            ])
            ->values()
            ->all();
    }
}
