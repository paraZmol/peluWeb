<?php

namespace App\Actions\Accounting;

use App\Data\Accounting\ExpenseInput;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Salon;
use App\Models\User;
use App\Support\Images\ImageStore;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Records an expense or corrects one, with the photo of its receipt.
 */
class SaveExpense
{
    public const int RECEIPT_MAX_SIDE = 1600;

    /** Receipts carry tax ids and bank details: never on the public disk. */
    public const string DISK = 'local';

    private readonly ImageStore $images;

    public function __construct()
    {
        $this->images = new ImageStore(self::DISK);
    }

    /**
     * Deletes the receipt's file and forgets it on the expense.
     */
    public function removeReceipt(Expense $expense): void
    {
        $this->images->delete($expense->receipt_path);
        $expense->receipt_path = null;
        $expense->save();
    }

    public function handle(Salon $salon, ExpenseInput $input, ?User $by, ?Expense $expense = null): Expense
    {
        $expense ??= new Expense;
        $previousReceipt = $expense->receipt_path;
        $newReceipt = $input->receipt === null
            ? null
            : $this->images->storeFitted($input->receipt, "salons/{$salon->id}/receipts", self::RECEIPT_MAX_SIDE, self::RECEIPT_MAX_SIDE);

        try {
            $expense->salon_id = $salon->id;
            $expense->expense_category_id = $this->categoryOf($salon, $input->categoryId);
            $expense->amount_cents = $input->amountCents;
            $expense->method = $input->method;
            // Correcting other fields must not move it to another moment.
            if (! $expense->exists || $expense->paid_at->setTimezone($salon->timezone)->toDateString() !== $input->localDate) {
                $expense->paid_at = $input->paidAt($salon->timezone, CarbonImmutable::now());
            }
            $expense->description = $input->description;
            $expense->recorded_by ??= $by?->id;

            if ($newReceipt !== null || $input->removeReceipt) {
                $expense->receipt_path = $newReceipt;
            }

            $expense->save();
        } catch (Throwable $exception) {
            $this->images->delete($newReceipt);

            throw $exception;
        }

        if ($expense->receipt_path !== $previousReceipt) {
            $this->images->delete($previousReceipt);
        }

        return $expense;
    }

    /**
     * Only a category of this same salon; anything else leaves it uncategorized.
     */
    private function categoryOf(Salon $salon, ?int $categoryId): ?int
    {
        if ($categoryId === null) {
            return null;
        }

        return ExpenseCategory::withoutGlobalScope('salon')->where('salon_id', $salon->id)->whereKey($categoryId)->value('id');
    }
}
