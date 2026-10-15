<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a salon pays for. Plans only differ by how many professionals with
 * an agenda the salon may have; every feature is in every plan.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $price_cents Monthly, IGV included.
 * @property int|null $founder_price_cents Kept by a founding salon while it keeps paying.
 * @property int $max_staff Active professionals (with an agenda).
 * @property bool $is_public Shown on the landing; the rest is sold by hand.
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'price_cents', 'founder_price_cents', 'max_staff', 'is_public', 'position'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /** What a trial gives, and what a salon without a plan is treated as. */
    public const string TRIAL_CODE = 'equipo';

    /** Months a yearly payment covers for the price of ten. */
    public const int YEAR_MONTHS = 12;

    public const int YEAR_PAID_MONTHS = 10;

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public static function trial(): self
    {
        return self::query()->where('code', self::TRIAL_CODE)->firstOrFail();
    }

    /**
     * @return HasMany<Salon, $this>
     */
    public function salons(): HasMany
    {
        return $this->hasMany(Salon::class);
    }

    /**
     * What this salon pays per month: the founder price if it holds one.
     */
    public function monthlyCentsFor(Salon $salon): int
    {
        return $salon->founder_since !== null && $this->founder_price_cents !== null
            ? $this->founder_price_cents
            : $this->price_cents;
    }
}
