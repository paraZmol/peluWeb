<?php

namespace App\Models;

use App\Enums\ServiceCategory;
use App\Enums\ServiceTarget;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform catalog entry (docs/catalogo-servicios.md). Not salon scoped:
 * every salon reads from the same catalog and copies into `services`.
 *
 * @property int $id
 * @property string $code
 * @property ServiceCategory $category
 * @property string $name
 * @property string|null $name_en
 * @property string $description
 * @property ServiceTarget $target
 * @property int $active_minutes
 * @property int $idle_minutes
 * @property int $finish_minutes
 * @property int $price_min_cents
 * @property int $price_max_cents
 * @property bool $price_varies_by_length
 * @property bool $requires_evaluation
 * @property bool $requires_patch_test
 * @property bool $is_addon
 * @property list<string>|null $tags
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ServiceTemplate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'target' => ServiceTarget::class,
            'price_varies_by_length' => 'boolean',
            'requires_evaluation' => 'boolean',
            'requires_patch_test' => 'boolean',
            'is_addon' => 'boolean',
            'tags' => 'array',
        ];
    }

    /**
     * A sensible starting price for a salon: the middle of the Lima range,
     * rounded to whole soles.
     */
    public function suggestedPriceCents(): int
    {
        return (int) (round(($this->price_min_cents + $this->price_max_cents) / 2 / 100) * 100);
    }
}
