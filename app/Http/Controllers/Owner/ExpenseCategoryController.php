<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Accounting\ManageExpenseCategories;
use App\Concerns\SalonValidationRules;
use App\Models\ExpenseCategory;
use App\Support\CurrentSalon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The salon's own kinds of expense. `{category}` is filtered by the
 * current salon (BelongsToSalon).
 */
class ExpenseCategoryController extends SalonAreaController
{
    use SalonValidationRules;

    public function __construct(private readonly ManageExpenseCategories $categories) {}

    public function store(Request $request): RedirectResponse
    {
        $salon = $this->salon('manageCash');
        $this->categories->add($salon, $this->validName($request));
        $this->toast('Categoría agregada.');

        return back();
    }

    public function update(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $this->salon('manageCash');
        $this->categories->rename($category, $this->validName($request, $category));
        $this->toast('Categoría actualizada.');

        return back();
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        $this->salon('manageCash');
        $this->categories->remove($category);
        $this->toast('Categoría eliminada. Sus gastos quedaron sin categoría.');

        return back();
    }

    private function validName(Request $request, ?ExpenseCategory $except = null): string
    {
        $unique = Rule::unique('expense_categories', 'name')
            ->where('salon_id', app(CurrentSalon::class)->id())
            ->ignore($except?->id);

        return $request->validate(
            ['name' => ['required', 'string', 'min:2', 'max:40', self::PLAIN_TEXT, $unique]],
            ['name.unique' => 'Ya tienes una categoría con ese nombre.'],
            ['name' => 'nombre'],
        )['name'];
    }
}
