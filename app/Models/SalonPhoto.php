<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Support\Images\ImageStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $salon_id
 * @property string $path
 * @property string|null $caption
 * @property int $position
 * @property-read string|null $url
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SalonPhoto extends Model
{
    use BelongsToSalon;

    public const int MAX_PER_SALON = 30;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'caption' => null,
        'position' => 0,
    ];

    /**
     * @return Attribute<string|null, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): ?string => app(ImageStore::class)->url($this->path));
    }
}
