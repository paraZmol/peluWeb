<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money the salon spent. Written only by App\Actions\Accounting\SaveExpense.
 *
 * @property int $id
 * @property int $salon_id
 * @property int|null $expense_category_id
 * @property int $amount_cents
 * @property PaymentMethod $method
 * @property CarbonImmutable $paid_at
 * @property string|null $description
 * @property string|null $receipt_path
 * @property int|null $recorded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property bool|null $settlement_exists Only when loaded with `withExists('settlement')`.
 * @property-read ExpenseCategory|null $category
 */
class Expense extends Model
{
    use BelongsToSalon;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * The commission settlement this expense is the payment of, if any.
     *
     * @return HasOne<CommissionSettlement, $this>
     */
    public function settlement(): HasOne
    {
        return $this->hasOne(CommissionSettlement::class);
    }
}
