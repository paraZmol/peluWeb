<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A calendar day (salon local date) in which the whole salon is closed.
 *
 * @property int $id
 * @property int $salon_id
 * @property CarbonImmutable $date
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SalonHoliday extends Model
{
    use BelongsToSalon;

    public const int MAX_PER_SALON = 60;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
