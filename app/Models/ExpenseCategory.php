<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of expense, named by the salon.
 *
 * @property int $id
 * @property int $salon_id
 * @property string $name
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ExpenseCategory extends Model
{
    use BelongsToSalon;

    public const int MAX_PER_SALON = 20;

    /** What a new salon starts with. */
    public const array DEFAULTS = ['Alquiler', 'Productos e insumos', 'Luz, agua e internet', 'Sueldos y comisiones', 'Otros'];

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
