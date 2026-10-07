<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\TimeOffReason;
use Carbon\CarbonImmutable;
use Database\Factories\StaffTimeOffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period (UTC) in which a professional does not attend: vacation, leave
 * or a manual block.
 *
 * @property int $id
 * @property int $salon_id
 * @property int $staff_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property TimeOffReason $reason
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class StaffTimeOff extends Model
{
    use BelongsToSalon;

    /** @use HasFactory<StaffTimeOffFactory> */
    use HasFactory;

    public const int MAX_DAYS = 366;

    protected $table = 'staff_time_off';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['note' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reason' => TimeOffReason::class,
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
