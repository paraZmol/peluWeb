<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One opening-to-closing of the salon's till. Written only by
 * App\Actions\Accounting\OpenCashSession and CloseCashSession.
 *
 * @property int $id
 * @property int $salon_id
 * @property CarbonImmutable $opened_at
 * @property int|null $opened_by
 * @property int $opening_cash_cents
 * @property CarbonImmutable|null $closed_at
 * @property int|null $closed_by
 * @property int|null $expected_cash_cents
 * @property int|null $counted_cash_cents
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CashSession extends Model
{
    use BelongsToSalon;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Counted minus expected: negative when cash is missing. Null while open.
     */
    public function differenceCents(): ?int
    {
        return $this->counted_cash_cents === null || $this->expected_cash_cents === null
            ? null
            : $this->counted_cash_cents - $this->expected_cash_cents;
    }
}
