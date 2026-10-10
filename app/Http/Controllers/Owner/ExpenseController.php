<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Accounting\MonthlyExpenses;
use App\Actions\Accounting\SaveExpense;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentException;
use App\Http\Requests\Owner\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\CommissionSettlement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Images\ImageStore;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What the salon spends. Everything here needs the cash permission;
 * `{expense}` is filtered by the current salon (BelongsToSalon).
 */
class ExpenseController extends SalonAreaController
{
    public function index(Request $request, MonthlyExpenses $monthly): Response
    {
        $salon = $this->salon('manageCash');
        $found = $monthly->handle($salon, $request->query('mes'));

        return Inertia::render('owner/expenses/index', [
            'month' => $found['month'],
            'currentMonth' => $salon->localNow()->format('Y-m'),
            'today' => $salon->localNow()->toDateString(),
            'expenses' => $found['expenses']->map(fn (Expense $expense) => (new ExpenseResource($expense, $salon->timezone))->resolve($request)),
            'total' => Money::toAmount((int) $found['expenses']->sum('amount_cents')),
            'categories' => ExpenseCategory::query()->orderBy('position')->orderBy('id')->get(['id', 'name']),
            'paymentMethods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
        ]);
    }

    public function store(ExpenseRequest $request, SaveExpense $save): RedirectResponse
    {
        $save->handle($this->salon('manageCash'), $request->expense(), $request->user());
        $this->toast('Gasto registrado.');

        return back();
    }

    public function update(ExpenseRequest $request, Expense $expense, SaveExpense $save): RedirectResponse
    {
        $this->ensureEditable($expense);
        $save->handle($this->salon('manageCash'), $request->expense(), $request->user(), $expense);
        $this->toast('Gasto actualizado.');

        return back();
    }

    public function destroy(Expense $expense, ImageStore $images): RedirectResponse
    {
        $this->salon('manageCash');
        $this->ensureEditable($expense);
        $images->delete($expense->receipt_path);
        $expense->forceFill(['receipt_path' => null])->delete();
        $this->toast('Gasto eliminado.');

        return back();
    }

    /**
     * The payment of a commission settlement stays as it was recorded:
     * changing it here would leave the settlement saying something else.
     */
    private function ensureEditable(Expense $expense): void
    {
        if (CommissionSettlement::query()->where('expense_id', $expense->id)->exists()) {
            throw PaymentException::expenseIsSettlement();
        }
    }
}
