<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stretch (UTC) in which a professional is busy with a booking. Whether
 * it still counts depends on the booking's status (Booking::scopeHoldingSlot).
 *
 * @property int $id
 * @property int $booking_id
 * @property int $staff_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 */
class BookingSegment extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
