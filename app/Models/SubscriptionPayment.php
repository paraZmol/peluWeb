<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subscription payment the platform received from a salon. Recorded by
 * hand by the platform admin; never edited afterwards.
 *
 * @property int $id
 * @property int $salon_id
 * @property int $plan_id
 * @property int $months
 * @property int $amount_cents
 * @property PaymentMethod $method
 * @property CarbonImmutable $covers_from
 * @property CarbonImmutable $covers_until
 * @property bool $is_founder
 * @property int|null $recorded_by
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SubscriptionPayment extends Model
{
    use BelongsToSalon;

    public const int NOTE_MAX_LENGTH = 200;

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'covers_from' => 'date',
            'covers_until' => 'date',
            'is_founder' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
