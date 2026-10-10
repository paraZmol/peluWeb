<?php

namespace App\Actions\Accounting;

use App\Models\Expense;
use App\Models\Salon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The expenses of one month on the salon's calendar, newest first.
 */
class MonthlyExpenses
{
    /**
     * @param  string|null  $month  "Y-m"; anything else means the current month
     * @return array{month: string, expenses: Collection<int, Expense>}
     */
    public function handle(Salon $salon, ?string $month): array
    {
        $start = $this->startOf($salon, $month);

        $expenses = Expense::query()
            ->with('category')
            ->withExists('settlement')
            ->where('paid_at', '>=', $start->utc())
            ->where('paid_at', '<', $start->addMonth()->utc())
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        return ['month' => $start->format('Y-m'), 'expenses' => $expenses];
    }

    private function startOf(Salon $salon, ?string $month): CarbonImmutable
    {
        $thisMonth = $salon->localNow()->startOfMonth();

        if ($month === null || preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $month) !== 1) {
            return $thisMonth;
        }

        $asked = CarbonImmutable::parse("{$month}-01 00:00", $salon->timezone);

        return $asked->greaterThan($thisMonth) ? $thisMonth : $asked;
    }
}
