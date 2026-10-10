<?php

namespace App\Actions\Accounting;

use App\Data\Accounting\Period;
use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Salon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Earnings and losses of a salon in a period, in céntimos.
 *
 * Income is what completed visits ended up costing (price − discount) plus
 * the deposits kept from cancellations and no-shows; a visit counts on the
 * day it took place. Tips are shown apart: they belong to whoever attended.
 * Expenses count on the day they were paid, commissions included.
 */
class SalonReport
{
    private const int TOP_SERVICES = 10;

    /**
     * @return array<string, mixed>
     */
    public function handle(Salon $salon, Period $period): array
    {
        return [
            'totals' => $this->totals($salon, $period),
            'previous' => $this->totals($salon, $period->previous()),
            'days' => $this->days($salon, $period),
            'byCategory' => $this->byCategory($salon, $period),
            'byStaff' => $this->byStaff($salon, $period),
            'byService' => $this->byService($salon, $period),
            'byMethod' => $this->byMethod($salon, $period),
            'lost' => $this->lost($salon, $period),
            'clients' => $this->clients($salon, $period),
        ];
    }

    /**
     * @return array{incomeCents: int, servicesCents: int, keptDepositsCents: int, expensesCents: int, profitCents: int, tipsCents: int, discountsCents: int, visits: int}
     */
    private function totals(Salon $salon, Period $period): array
    {
        $visits = $this->completed($salon, $period)
            ->selectRaw('COUNT(*) as visits, COALESCE(SUM(charged_cents), 0) as services, COALESCE(SUM(tip_cents), 0) as tips, COALESCE(SUM(discount_cents), 0) as discounts')
            ->toBase()->first();
        $services = (int) ($visits->services ?? 0);
        $kept = (int) $this->keptDeposits($salon, $period)->sum('deposit_cents');
        $expenses = (int) $this->expenses($salon, $period)->sum('amount_cents');

        return [
            'incomeCents' => $services + $kept,
            'servicesCents' => $services,
            'keptDepositsCents' => $kept,
            'expensesCents' => $expenses,
            'profitCents' => $services + $kept - $expenses,
            'tipsCents' => (int) ($visits->tips ?? 0),
            'discountsCents' => (int) ($visits->discounts ?? 0),
            'visits' => (int) ($visits->visits ?? 0),
        ];
    }

    /**
     * One entry per calendar day of the period, also the empty ones.
     *
     * @return list<array{date: string, incomeCents: int, expensesCents: int}>
     */
    private function days(Salon $salon, Period $period): array
    {
        $income = [];
        $expenses = [];

        for ($day = $period->from; $day->lessThanOrEqualTo($period->to); $day = $day->addDay()) {
            $income[$day->format('Y-m-d')] = 0;
            $expenses[$day->format('Y-m-d')] = 0;
        }

        $local = fn (CarbonImmutable $instant): string => $instant->setTimezone($salon->timezone)->format('Y-m-d');

        foreach ($this->completed($salon, $period)->get(['id', 'starts_at', 'charged_cents']) as $booking) {
            $income[$local($booking->starts_at)] += (int) $booking->charged_cents;
        }

        foreach ($this->keptDeposits($salon, $period)->get(['id', 'starts_at', 'deposit_cents']) as $booking) {
            $income[$local($booking->starts_at)] += $booking->deposit_cents;
        }

        foreach ($this->expenses($salon, $period)->get(['id', 'paid_at', 'amount_cents']) as $expense) {
            $expenses[$local($expense->paid_at)] += $expense->amount_cents;
        }

        return array_map(
            fn (string $date): array => ['date' => $date, 'incomeCents' => $income[$date], 'expensesCents' => $expenses[$date]],
            array_keys($income),
        );
    }

    /**
     * @return array<int, array{name: string, cents: int}>
     */
    private function byCategory(Salon $salon, Period $period): array
    {
        return $this->expenses($salon, $period)
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw("COALESCE(expense_categories.name, 'Sin categoría') as name, SUM(expenses.amount_cents) as cents")
            ->groupBy('name')->orderByDesc('cents')->toBase()->get()
            ->map(fn (object $row): array => ['name' => (string) $row->name, 'cents' => (int) $row->cents])
            ->values()->all();
    }

    /**
     * @return array<int, array{name: string, visits: int, salesCents: int, commissionCents: int, tipsCents: int}>
     */
    private function byStaff(Salon $salon, Period $period): array
    {
        return $this->completed($salon, $period)
            ->join('staff', 'staff.id', '=', 'bookings.staff_id')
            ->selectRaw('staff.name as name, COUNT(*) as visits, COALESCE(SUM(bookings.charged_cents), 0) as sales, COALESCE(SUM(bookings.commission_cents), 0) as commission, COALESCE(SUM(bookings.tip_cents), 0) as tips')
            ->groupBy('staff.id', 'staff.name')->orderByDesc('sales')->toBase()->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'visits' => (int) $row->visits,
                'salesCents' => (int) $row->sales,
                'commissionCents' => (int) $row->commission,
                'tipsCents' => (int) $row->tips,
            ])
            ->values()->all();
    }

    /**
     * The most sold services, at their list price (discounts are per visit).
     *
     * @return array<int, array{name: string, count: int, cents: int}>
     */
    private function byService(Salon $salon, Period $period): array
    {
        return $this->completed($salon, $period)
            ->join('booking_services', 'booking_services.booking_id', '=', 'bookings.id')
            ->selectRaw('booking_services.name as name, COUNT(*) as times, SUM(booking_services.price_cents) as cents')
            ->groupBy('booking_services.name')->orderByDesc('cents')->limit(self::TOP_SERVICES)->toBase()->get()
            ->map(fn (object $row): array => ['name' => (string) $row->name, 'count' => (int) $row->times, 'cents' => (int) $row->cents])
            ->values()->all();
    }

    /**
     * Money that came in by method (deposits and charges, tips included),
     * minus deposits given back. Counted when it changed hands.
     *
     * @return array<int, array{value: string, label: string, cents: int}>
     */
    private function byMethod(Salon $salon, Period $period): array
    {
        $rows = Payment::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('paid_at', '>=', $period->startsAtUtc())
            ->where('paid_at', '<', $period->endsBeforeUtc())
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

    /**
     * Visits that did not happen and what they would have brought in.
     *
     * @return array{cancelled: int, cancelledCents: int, noShows: int, noShowCents: int}
     */
    private function lost(Salon $salon, Period $period): array
    {
        $rows = $this->inPeriod($salon, $period)
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
    private function clients(Salon $salon, Period $period): array
    {
        $attended = $this->completed($salon, $period)->distinct()->pluck('salon_client_id');

        $returning = Booking::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->where('status', BookingStatus::Completed)
            ->where('starts_at', '<', $period->startsAtUtc())
            ->whereIn('salon_client_id', $attended)
            ->distinct()->count('salon_client_id');

        return ['new' => $attended->count() - $returning, 'returning' => $returning];
    }

    /**
     * @return Builder<Booking>
     */
    private function inPeriod(Salon $salon, Period $period): Builder
    {
        return Booking::withoutGlobalScope('salon')
            ->where('bookings.salon_id', $salon->id)
            ->where('bookings.starts_at', '>=', $period->startsAtUtc())
            ->where('bookings.starts_at', '<', $period->endsBeforeUtc());
    }

    /**
     * @return Builder<Booking>
     */
    private function completed(Salon $salon, Period $period): Builder
    {
        return $this->inPeriod($salon, $period)->where('bookings.status', BookingStatus::Completed);
    }

    /**
     * Paid deposits of visits that did not happen and are not owed back.
     *
     * @return Builder<Booking>
     */
    private function keptDeposits(Salon $salon, Period $period): Builder
    {
        return $this->inPeriod($salon, $period)
            ->whereIn('bookings.status', [BookingStatus::Cancelled, BookingStatus::NoShow])
            ->whereNotNull('bookings.deposit_paid_at')
            ->where('bookings.deposit_refundable', false);
    }

    /**
     * @return Builder<Expense>
     */
    private function expenses(Salon $salon, Period $period): Builder
    {
        return Expense::withoutGlobalScope('salon')
            ->where('expenses.salon_id', $salon->id)
            ->where('expenses.paid_at', '>=', $period->startsAtUtc())
            ->where('expenses.paid_at', '<', $period->endsBeforeUtc());
    }
}
