<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One service of a booking, with its name, price and durations frozen at
 * booking time. Reached only through its booking, which is salon-scoped.
 *
 * @property int $id
 * @property int $booking_id
 * @property int|null $service_id
 * @property string $name
 * @property int $price_cents
 * @property int $active_minutes
 * @property int $idle_minutes
 * @property int $finish_minutes
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class BookingService extends Model
{
    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
