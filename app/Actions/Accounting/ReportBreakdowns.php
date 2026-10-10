<?php

namespace App\Actions\Accounting;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;

/**
 * A report's figures split by what the owner wants to compare: expense
 * category, professional, service and payment method. In céntimos.
 */
class ReportBreakdowns
{
    private const int TOP_SERVICES = 10;

    /**
     * @return array<int, array{name: string, cents: int}>
     */
    public function byCategory(ReportScope $scope): array
    {
        return $scope->expenses()
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw("COALESCE(expense_categories.name, 'Sin categoría') as name, SUM(expenses.amount_cents) as cents")
            ->groupBy('name')->orderByDesc('cents')->toBase()->get()
            ->map(fn (object $row): array => ['name' => (string) $row->name, 'cents' => (int) $row->cents])
            ->values()->all();
    }

    /**
     * @return array<int, array{name: string, visits: int, salesCents: int, commissionCents: int}>
     */
    public function byStaff(ReportScope $scope): array
    {
        return $scope->completed()
            ->join('staff', 'staff.id', '=', 'bookings.staff_id')
            ->selectRaw('staff.name as name, COUNT(*) as visits, COALESCE(SUM(bookings.charged_cents), 0) as sales, COALESCE(SUM(bookings.commission_cents), 0) as commission')
            ->groupBy('staff.id', 'staff.name')->orderByDesc('sales')->toBase()->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'visits' => (int) $row->visits,
                'salesCents' => (int) $row->sales,
                'commissionCents' => (int) $row->commission,
            ])
            ->values()->all();
    }

    /**
     * The most sold services, at their list price (discounts are per visit).
     *
     * @return array<int, array{name: string, count: int, cents: int}>
     */
    public function byService(ReportScope $scope): array
    {
        return $scope->completed()
            ->join('booking_services', 'booking_services.booking_id', '=', 'bookings.id')
            ->selectRaw('booking_services.name as name, COUNT(*) as times, SUM(booking_services.price_cents) as cents')
            ->groupBy('booking_services.name')->orderByDesc('cents')->limit(self::TOP_SERVICES)->toBase()->get()
            ->map(fn (object $row): array => ['name' => (string) $row->name, 'count' => (int) $row->times, 'cents' => (int) $row->cents])
            ->values()->all();
    }

    /**
     * Money that came in by method (deposits and charges), minus deposits
     * given back. Counted when it changed hands.
     *
     * @return array<int, array{value: string, label: string, cents: int}>
     */
    public function byMethod(ReportScope $scope): array
    {
        $rows = $scope->payments()
            ->selectRaw('method, kind, SUM(amount_cents) as cents')
            ->groupBy('method', 'kind')->toBase()->get();
        $net = [];

        foreach ($rows as $row) {
            $sign = PaymentKind::from($row->kind)->isOutgoing() ? -1 : 1;
            $net[$row->method] = ($net[$row->method] ?? 0) + $sign * (int) $row->cents;
        }

        arsort($net);

        return array_map(
            fn (string $method, int $cents): array => ['value' => $method, 'label' => PaymentMethod::from($method)->label(), 'cents' => $cents],
            array_keys($net),
            array_values($net),
        );
    }
}
