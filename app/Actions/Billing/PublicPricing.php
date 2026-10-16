<?php

namespace App\Actions\Billing;

use App\Models\Plan;
use App\Models\Salon;

/**
 * The prices the landing shows, from the plans table, and the founder
 * offer only while it is open: its places and last day are written in the
 * offer (Indecopi), so the line disappears by itself when either runs out.
 */
class PublicPricing
{
    /**
     * @return array{plans: array<int, array{code: string, name: string, priceCents: int, founderPriceCents: int|null, maxStaff: int}>, founder: array{slotsLeft: int, endsOn: string|null}|null}
     */
    public function handle(): array
    {
        $plans = Plan::query()->where('is_public', true)->orderBy('position')->get()
            ->map(fn (Plan $plan): array => [
                'code' => $plan->code,
                'name' => $plan->name,
                'priceCents' => $plan->price_cents,
                'founderPriceCents' => $plan->founder_price_cents,
                'maxStaff' => $plan->max_staff,
            ])
            ->values()
            ->all();

        return ['plans' => $plans, 'founder' => $this->founder()];
    }

    /**
     * @return array{slotsLeft: int, endsOn: string|null}|null
     */
    private function founder(): ?array
    {
        if (! RecordSubscriptionPayment::founderOfferIsOpen(RecordSubscriptionPayment::todayInLima())) {
            return null;
        }

        $endsOn = RecordSubscriptionPayment::founderEndsOn();

        return [
            'slotsLeft' => (int) config('billing.founder.slots') - Salon::query()->whereNotNull('founder_since')->count(),
            'endsOn' => is_string($endsOn) ? $endsOn : null,
        ];
    }
}
