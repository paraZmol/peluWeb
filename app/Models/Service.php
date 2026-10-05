<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\ServiceCategory;
use App\Enums\ServiceTarget;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * A service a salon offers, with its own price and duration segments.
 *
 * @property int $id
 * @property int $salon_id
 * @property int|null $service_template_id
 * @property ServiceCategory $category
 * @property string $name
 * @property string|null $description
 * @property ServiceTarget $target
 * @property int $price_cents
 * @property int $active_minutes
 * @property int $idle_minutes
 * @property int $finish_minutes
 * @property bool $is_addon
 * @property bool $requires_evaluation
 * @property bool $requires_patch_test
 * @property bool $is_active
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, ServiceVariant> $variants
 * @property-read ServiceTemplate|null $template
 */
#[Fillable([
    'category', 'name', 'description', 'target', 'price_cents',
    'active_minutes', 'idle_minutes', 'finish_minutes',
    'is_addon', 'requires_evaluation', 'requires_patch_test', 'is_active',
])]
class Service extends Model
{
    use BelongsToSalon;

    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'idle_minutes' => 0,
        'finish_minutes' => 0,
        'is_addon' => false,
        'requires_evaluation' => false,
        'requires_patch_test' => false,
        'is_active' => true,
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'target' => ServiceTarget::class,
            'is_addon' => 'boolean',
            'requires_evaluation' => 'boolean',
            'requires_patch_test' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ServiceVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ServiceVariant::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<ServiceTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ServiceTemplate::class, 'service_template_id');
    }

    /**
     * Total time from start to end, including the idle (processing) gap.
     */
    public function totalMinutes(): int
    {
        return $this->active_minutes + $this->idle_minutes + $this->finish_minutes;
    }
}
