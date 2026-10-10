<?php

namespace App\Http\Resources;

use App\Models\Expense;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    public function __construct(Expense $expense, private readonly string $timezone)
    {
        parent::__construct($expense);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->paid_at->setTimezone($this->timezone)->format('Y-m-d'),
            'amount' => Money::toAmount($this->amount_cents),
            'amountCents' => $this->amount_cents,
            'method' => $this->method->value,
            'methodLabel' => $this->method->label(),
            'categoryId' => $this->expense_category_id,
            'categoryName' => $this->category?->name,
            'description' => $this->description,
            // Paid from Comisiones: it is changed there, not from the expenses list.
            'isSettlement' => (bool) $this->settlement_exists,
            // The file's own random name busts the browser cache when the photo changes.
            'receiptUrl' => $this->receipt_path === null ? null : route('expenses.receipt', ['expense' => $this->id, 'v' => substr($this->receipt_path, -13, 8)]),
        ];
    }
}
