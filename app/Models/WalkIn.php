<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\WalkInStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone who walked in without a booking and is waiting for a turn.
 * Written down by the team; the person does nothing on the site.
 *
 * @property int $id
 * @property int $salon_id
 * @property CarbonImmutable $day
 * @property string $name
 * @property int|null $service_id
 * @property int|null $staff_id
 * @property WalkInStatus $status
 * @property CarbonImmutable|null $called_at
 * @property CarbonImmutable|null $closed_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property-read Service|null $service
 * @property-read Staff|null $staff
 */
class WalkIn extends Model
{
    use BelongsToSalon;

    public const int NAME_MAX_LENGTH = 80;

    /** People in line at once: more than this is not a line anyone keeps. */
    public const int MAX_OPEN = 60;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date:Y-m-d',
            'status' => WalkInStatus::class,
            'called_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
