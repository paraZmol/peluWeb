<?php

namespace App\Concerns;

use App\Models\Salon;
use App\Support\CurrentSalon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isolates a model per salon: every query is filtered by the current salon
 * and new records are stamped with it.
 *
 * Without a current salon (console, platform admin) no filter is applied,
 * which is why salon-area routes must use the `salon` middleware.
 *
 * @mixin Model
 */
trait BelongsToSalon
{
    public static function bootBelongsToSalon(): void
    {
        static::addGlobalScope('salon', function (Builder $query): void {
            $salonId = app(CurrentSalon::class)->id();

            if ($salonId !== null) {
                $query->where($query->qualifyColumn('salon_id'), $salonId);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('salon_id') === null) {
                $model->setAttribute('salon_id', app(CurrentSalon::class)->id());
            }
        });
    }

    /**
     * @return BelongsTo<Salon, $this>
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }
}
