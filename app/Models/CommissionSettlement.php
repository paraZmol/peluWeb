<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One professional's commissions for a period, paid at once. Written only
 * by App\Actions\Accounting\SettleCommissions.
 *
 * @property int $id
 * @property int $salon_id
 * @property int $staff_id
 * @property CarbonImmutable $period_from
 * @property CarbonImmutable $period_to
 * @property int $total_cents
 * @property int $bookings_count
 * @property int|null $expense_id
 * @property CarbonImmutable $paid_at
 * @property int|null $paid_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Staff $staff
 */
class CommissionSettlement extends Model
{
    use BelongsToSalon;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }
}
