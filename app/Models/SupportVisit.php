<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A look at a salon from the inside by the platform admin, read only and
 * audited. A platform record: it belongs to no salon's data.
 *
 * @property int $id
 * @property int $admin_id
 * @property int $salon_id
 * @property string $reason
 * @property string|null $ip
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SupportVisit extends Model
{
    /** A visit ends by itself after this long, in case nobody leaves it. */
    public const int MAX_MINUTES = 120;

    public const int REASON_MAX_LENGTH = 200;

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * @return BelongsTo<Salon, $this>
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function isOpenFor(User $user): bool
    {
        return $this->admin_id === $user->id
            && $this->ended_at === null
            && $this->started_at->addMinutes(self::MAX_MINUTES)->isFuture();
    }
}
