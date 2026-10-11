<?php

namespace App\Concerns;

use App\Models\Salon;
use App\Support\CurrentSalon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Isolates a model per salon: every query is filtered by the current salon
 * and new records are stamped with it.
 *
 * Fail closed: without a current salon queries match nothing, unless
 * CurrentSalon::bypass() was called on purpose (platform panel, console).
 * Code that legitimately crosses salons (e.g. accepting an invitation by
 * its token) must say so with withoutGlobalScope('salon').
 *
 * @mixin Model
 */
trait BelongsToSalon
{
    public static function bootBelongsToSalon(): void
    {
        static::addGlobalScope('salon', function (Builder $query): void {
            $currentSalon = app(CurrentSalon::class);

            // The platform panel opted out on purpose: it sees every salon,
            // also when its admin happens to belong to one.
            if ($currentSalon->isBypassed()) {
                return;
            }

            if ($currentSalon->has()) {
                $query->where($query->qualifyColumn('salon_id'), $currentSalon->id());
            } else {
                $query->whereRaw('1 = 0');
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('salon_id') !== null) {
                return;
            }

            $salonId = app(CurrentSalon::class)->id()
                ?? throw new LogicException(class_basename($model).' needs a salon_id and there is no current salon.');

            $model->setAttribute('salon_id', $salonId);
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
