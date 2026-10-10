<?php

namespace App\Actions\Accounting;

use App\Data\Accounting\Period;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Salon;
use Carbon\CarbonImmutable;

/**
 * Earnings and losses of a salon in a period, in céntimos.
 *
 * Income is what completed visits ended up costing (price − discount) plus
 * the deposits kept from cancellations and no-shows; a visit counts on the
 * day it took place. Tips are not the salon's and are not recorded.
 * Expenses count on the day they were paid, commissions included.
 */
class SalonReport
{
    public function __construct(
        private readonly ReportBreakdowns $breakdowns,
        private readonly StaffOccupancy $occupancy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Salon $salon, Period $period): array
    {
        $scope = new ReportScope($salon, $period);

        return [
            'totals' => $this->totals($scope),
            'previous' => $this->totals(new ReportScope($salon, $period->previous())),
            'days' => $this->days($scope),
            'byCategory' => $this->breakdowns->byCategory($scope),
            'byStaff' => $this->breakdowns->byStaff($scope),
            'byService' => $this->breakdowns->byService($scope),
            'byMethod' => $this->breakdowns->byMethod($scope),
            'lost' => $this->lost($scope),
            'clients' => $this->clients($scope),
            'occupancy' => $this->occupancy->handle($scope),
        ];
    }

    /**
     * @return array{incomeCents: int, servicesCents: int, keptDepositsCents: int, expensesCents: int, profitCents: int, discountsCents: int, visits: int}
     */
    private function totals(ReportScope $scope): array
    {
        $visits = $scope->completed()
            ->selectRaw('COUNT(*) as visits, COALESCE(SUM(charged_cents), 0) as services, COALESCE(SUM(discount_cents), 0) as discounts')
            ->toBase()->first();
        $services = (int) ($visits->services ?? 0);
        $kept = (int) $scope->keptDeposits()->sum('deposit_cents');
        $expenses = (int) $scope->expenses()->sum('amount_cents');

        return [
            'incomeCents' => $services + $kept,
            'servicesCents' => $services,
            'keptDepositsCents' => $kept,
            'expensesCents' => $expenses,
            'profitCents' => $services + $kept - $expenses,
            'discountsCents' => (int) ($visits->discounts ?? 0),
            'visits' => (int) ($visits->visits ?? 0),
        ];
    }

    /**
     * One entry per calendar day of the period, also the empty ones.
     *
     * @return list<array{date: string, incomeCents: int, expensesCents: int}>
     */
    private function days(ReportScope $scope): array
    {
        $income = [];
        $expenses = [];

        for ($day = $scope->period->from; $day->lessThanOrEqualTo($scope->period->to); $day = $day->addDay()) {
            $income[$day->format('Y-m-d')] = 0;
            $expenses[$day->format('Y-m-d')] = 0;
        }

        $local = fn (CarbonImmutable $instant): string => $instant->setTimezone($scope->salon->timezone)->format('Y-m-d');

        foreach ($scope->completed()->get(['id', 'starts_at', 'charged_cents']) as $booking) {
            $income[$local($booking->starts_at)] += (int) $booking->charged_cents;
        }

        foreach ($scope->keptDeposits()->get(['id', 'starts_at', 'deposit_cents']) as $booking) {
            $income[$local($booking->starts_at)] += $booking->deposit_cents;
        }

        foreach ($scope->expenses()->get(['id', 'paid_at', 'amount_cents']) as $expense) {
            $expenses[$local($expense->paid_at)] += $expense->amount_cents;
        }

        return array_map(
            fn (string $date): array => ['date' => $date, 'incomeCents' => $income[$date], 'expensesCents' => $expenses[$date]],
            array_keys($income),
        );
    }

    /**
     * Visits that did not happen and what they would have brought in.
     *
     * @return array{cancelled: int, cancelledCents: int, noShows: int, noShowCents: int}
     */
    private function lost(ReportScope $scope): array
    {
        $rows = $scope->bookings()
            ->whereIn('status', [BookingStatus::Cancelled, BookingStatus::NoShow])
            ->selectRaw('status, COUNT(*) as times, COALESCE(SUM(price_cents), 0) as cents')
            ->groupBy('status')->toBase()->get()->keyBy('status');
        $cancelled = $rows->get(BookingStatus::Cancelled->value);
        $noShow = $rows->get(BookingStatus::NoShow->value);

        return [
            'cancelled' => (int) ($cancelled->times ?? 0),
            'cancelledCents' => (int) ($cancelled->cents ?? 0),
            'noShows' => (int) ($noShow->times ?? 0),
            'noShowCents' => (int) ($noShow->cents ?? 0),
        ];
    }

    /**
     * Among the people attended in the period: who came for the first time
     * and who had already been here.
     *
     * @return array{new: int, returning: int}
     */
    private function clients(ReportScope $scope): array
    {
        $attended = $scope->completed()->distinct()->pluck('salon_client_id');

        $returning = Booking::withoutGlobalScope('salon')
            ->where('salon_id', $scope->salon->id)
            ->where('status', BookingStatus::Completed)
            ->where('starts_at', '<', $scope->period->startsAtUtc())
            ->whereIn('salon_client_id', $attended)
            ->distinct()->count('salon_client_id');

        return ['new' => $attended->count() - $returning, 'returning' => $returning];
    }
}
