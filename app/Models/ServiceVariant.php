<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A price/duration option of a service, e.g. "Cabello largo".
 * Scoped through its service (which is salon scoped).
 *
 * @property int $id
 * @property int $service_id
 * @property string $label
 * @property int $price_cents
 * @property int $extra_minutes
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label', 'price_cents', 'extra_minutes', 'position'])]
class ServiceVariant extends Model
{
    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
