<?php

namespace App\Models;

use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonRole;
use App\Enums\SalonStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SalonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property BusinessType $business_type
 * @property Clientele $clientele
 * @property string|null $phone
 * @property string $timezone
 * @property string $currency
 * @property SalonStatus $status
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['name', 'business_type', 'clientele', 'phone', 'timezone'])]
class Salon extends Model
{
    /** @use HasFactory<SalonFactory> */
    use HasFactory, SoftDeletes;

    public const int TRIAL_DAYS = 30;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'clientele' => Clientele::class,
            'status' => SalonStatus::class,
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SalonMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(SalonMember::class);
    }

    /**
     * @return HasMany<SalonInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(SalonInvitation::class);
    }

    public function ownersCount(): int
    {
        return $this->members()->where('role', SalonRole::Owner)->count();
    }
}
