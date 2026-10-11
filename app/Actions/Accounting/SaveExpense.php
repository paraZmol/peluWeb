<?php

namespace App\Actions\Accounting;

use App\Data\Accounting\ExpenseInput;
use App\Exceptions\PaymentException;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Salon;
use App\Models\User;
use App\Support\Images\ImageStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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

    /**
     * Removes an expense and, once that is certain, its receipt.
     */
    public function delete(Expense $expense): void
    {
        $receipt = $expense->receipt_path;

        DB::transaction(function () use ($expense): void {
            $this->ensureTillStillOpen($expense);
            $expense->receipt_path = null;
            $expense->save();
            $expense->delete();
        });

        $this->images->delete($receipt);
    }

    public function handle(Salon $salon, ExpenseInput $input, ?User $by, ?Expense $expense = null): Expense
    {
        $expense ??= new Expense;
        $previousReceipt = $expense->receipt_path;
        $newReceipt = $input->receipt === null
            ? null
            : $this->images->storeFitted($input->receipt, "salons/{$salon->id}/receipts", self::RECEIPT_MAX_SIDE, self::RECEIPT_MAX_SIDE);

        try {
            DB::transaction(function () use ($salon, $input, $by, $expense, $newReceipt): void {
                $this->ensureTillStillOpen($expense);
                $expense->salon_id = $salon->id;
                $expense->expense_category_id = $this->categoryOf($salon, $input->categoryId);
                $expense->amount_cents = $input->amountCents;
                $expense->method = $input->method;
                // Correcting other fields must not move it to another moment.
                if (! $expense->exists || $expense->paid_at->setTimezone($salon->timezone)->toDateString() !== $input->localDate) {
                    $this->place($expense, $salon, $input);
                }
                $expense->description = $input->description;
                $expense->recorded_by ??= $by?->id;

                if ($newReceipt !== null || $input->removeReceipt) {
                    $expense->receipt_path = $newReceipt;
                }

                $expense->save();
            });
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

    /**
     * When it was paid and from which till: paid today it left the open
     * one; an expense of another day touches no session.
     */
    private function place(Expense $expense, Salon $salon, ExpenseInput $input): void
    {
        $now = CarbonImmutable::now();
        $paidAt = $input->paidAt($salon->timezone, $now);
        // Compared before storing: the attribute drops the fraction of a second.
        $isToday = $paidAt->equalTo($now);

        if (! $expense->exists) {
            $expense->cash_session_id = $isToday ? CashSession::lockOpenIdFor($salon->id) : null;
        } elseif (! $isToday) {
            // Moved to another day: it no longer left today's till. The
            // other way round is not allowed: a correction must not slip
            // an old expense into the till that is open now.
            $expense->cash_session_id = null;
        }

        $expense->paid_at = $paidAt;
    }

    /**
     * An expense that left a till can only change while that till is open:
     * a closed one froze what it expected, and changing its movements
     * afterwards would hide money. Takes the salon's row, like every
     * movement, so it cannot cross with the closing.
     */
    private function ensureTillStillOpen(Expense $expense): void
    {
        if (! $expense->exists || $expense->cash_session_id === null) {
            return;
        }

        if (CashSession::lockOpenIdFor($expense->salon_id) !== $expense->cash_session_id) {
            throw PaymentException::expenseInClosedCash();
        }
    }
}
