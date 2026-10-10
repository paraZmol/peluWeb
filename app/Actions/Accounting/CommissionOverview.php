<?php

namespace App\Actions\Accounting;

use App\Data\Accounting\Period;
use App\Models\CommissionSettlement;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Support\Money;
use Illuminate\Support\Collection;
use stdClass;

/**
 * What each professional earned and is still owed in a period, their
 * rules, and the latest settlements.
 */
class CommissionOverview
{
    private const int HISTORY = 20;

    /**
     * @return array{staff: array<int, array<string, mixed>>, history: array<int, array<string, mixed>>}
     */
    public function handle(Salon $salon, Period $period): array
    {
        $pending = SettleCommissions::pending($salon->id, $period)
            ->selectRaw('staff_id, COUNT(*) as visits, SUM(charged_cents) as sales, SUM(commission_cents) as commission')
            ->groupBy('staff_id')
            ->toBase()
            ->get()
            ->keyBy('staff_id');

        $staff = Staff::query()->with('services:id,name')->orderBy('position')->orderBy('id')->get()
            ->map(fn (Staff $member): array => $this->row($member, $pending))
            ->values()
            ->all();

        $history = CommissionSettlement::query()->with('staff')->latest('paid_at')->latest('id')->limit(self::HISTORY)->get()
            ->map(fn (CommissionSettlement $settlement): array => [
                'id' => $settlement->id,
                'staffName' => $settlement->staff->name,
                'from' => $settlement->period_from->format('Y-m-d'),
                'to' => $settlement->period_to->format('Y-m-d'),
                'total' => Money::toAmount($settlement->total_cents),
                'visits' => $settlement->bookings_count,
                'paidOn' => $settlement->paid_at->setTimezone($salon->timezone)->format('Y-m-d'),
            ])
            ->values()
            ->all();

        return ['staff' => $staff, 'history' => $history];
    }

    /**
     * @param  Collection<array-key, stdClass>  $pending
     * @return array<string, mixed>
     */
    private function row(Staff $member, Collection $pending): array
    {
        $owed = $pending->get($member->id);

        return [
            'id' => $member->id,
            'name' => $member->name,
            'isActive' => $member->is_active,
            'percent' => $member->commission_percent,
            'visits' => (int) ($owed->visits ?? 0),
            'sales' => Money::toAmount((int) ($owed->sales ?? 0)),
            'pending' => Money::toAmount((int) ($owed->commission ?? 0)),
            'pendingCents' => (int) ($owed->commission ?? 0),
            'services' => $member->services->map(fn (Service $service): array => $this->rule($service))->values()->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, percent: int|null, amount: string|null}
     */
    private function rule(Service $service): array
    {
        $pivot = $service->getRelationValue('pivot');
        $percent = $pivot?->getAttribute('commission_percent');
        $cents = $pivot?->getAttribute('commission_cents');

        return [
            'id' => $service->id,
            'name' => $service->name,
            'percent' => $percent === null ? null : (int) $percent,
            'amount' => $cents === null ? null : Money::toAmount((int) $cents),
        ];
    }
}
