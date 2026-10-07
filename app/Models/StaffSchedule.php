<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\ScheduleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One working-hours range of a professional on a weekday, in the salon's
 * local time. Written only by ReplaceWeeklySchedule.
 *
 * @property int $id
 * @property int $salon_id
 * @property int $staff_id
 * @property int $weekday ISO: 1 = Monday … 7 = Sunday
 * @property string $start_time "HH:MM:SS"
 * @property string $end_time "HH:MM:SS"
 * @property ScheduleAccess $access_level
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class StaffSchedule extends Model
{
    use BelongsToSalon;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['access_level' => ScheduleAccess::class];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** Start as "HH:MM". */
    public function start(): string
    {
        return substr($this->start_time, 0, 5);
    }

    /** End as "HH:MM". */
    public function end(): string
    {
        return substr($this->end_time, 0, 5);
    }
}
