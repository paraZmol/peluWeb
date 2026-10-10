<?php

namespace App\Actions\Accounting;

use App\Exceptions\PaymentException;
use App\Models\ExpenseCategory;
use App\Models\Salon;
use Illuminate\Support\Facades\DB;

/**
 * The salon's own list of expense kinds.
 */
class ManageExpenseCategories
{
    public function createDefaults(Salon $salon): void
    {
        foreach (ExpenseCategory::DEFAULTS as $position => $name) {
            $this->create($salon, $name, $position);
        }
    }

    public function add(Salon $salon, string $name): ExpenseCategory
    {
        return DB::transaction(function () use ($salon, $name): ExpenseCategory {
            // Lock the salon so two requests cannot both pass the limit.
            Salon::query()->lockForUpdate()->findOrFail($salon->id);
            $count = ExpenseCategory::withoutGlobalScope('salon')->where('salon_id', $salon->id)->count();

            if ($count >= ExpenseCategory::MAX_PER_SALON) {
                throw PaymentException::tooManyCategories(ExpenseCategory::MAX_PER_SALON);
            }

            return $this->create($salon, $name, $count);
        });
    }

    public function rename(ExpenseCategory $category, string $name): void
    {
        $category->name = $name;
        $category->save();
    }

    /**
     * Its expenses stay, uncategorized (the foreign key nulls them).
     */
    public function remove(ExpenseCategory $category): void
    {
        $category->delete();
    }

    private function create(Salon $salon, string $name, int $position): ExpenseCategory
    {
        $category = new ExpenseCategory;
        $category->salon_id = $salon->id;
        $category->name = $name;
        $category->position = $position;
        $category->save();

        return $category;
    }
}
