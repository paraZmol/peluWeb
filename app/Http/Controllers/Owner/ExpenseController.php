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
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the salon spends. Everything here needs the cash permission;
 * `{expense}` is filtered by the current salon (BelongsToSalon).
 */
class ExpenseController extends SalonAreaController
{
    public function index(Request $request, MonthlyExpenses $monthly): Response
    {
        $salon = $this->salon('manageCash');
        $found = $monthly->handle($salon, $this->text($request, 'mes'));

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
        $salon = $this->salon('manageCash');
        $this->ensureEditable($expense);
        $save->handle($salon, $request->expense(), $request->user(), $expense);
        $this->toast('Gasto actualizado.');

        return back();
    }

    public function destroy(Expense $expense, SaveExpense $save): RedirectResponse
    {
        $this->salon('manageCash');
        $this->ensureEditable($expense);
        $save->removeReceipt($expense);
        $expense->delete();
        $this->toast('Gasto eliminado.');

        return back();
    }

    /**
     * The receipt's photo, only for whoever handles this salon's cash.
     */
    public function receipt(Expense $expense): StreamedResponse
    {
        $this->salon('manageCash');
        abort_if($expense->receipt_path === null || ! Storage::disk(SaveExpense::DISK)->exists($expense->receipt_path), 404);

        return Storage::disk(SaveExpense::DISK)->response($expense->receipt_path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=3600',
        ]);
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
