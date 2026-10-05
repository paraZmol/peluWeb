<?php

namespace App\Models;

use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use Carbon\CarbonImmutable;
use Database\Factories\SalonMemberFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A user's role inside one salon (a row of `salon_user`).
 *
 * Nothing is mass assignable on purpose: roles and permissions are only
 * written by the team Actions.
 *
 * @property int $id
 * @property int $salon_id
 * @property int $user_id
 * @property SalonRole $role
 * @property Collection<int, SalonPermission>|null $permissions
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Salon|null $salon Null when the salon was soft-deleted.
 * @property-read User $user
 */
class SalonMember extends Model
{
    /** @use HasFactory<SalonMemberFactory> */
    use HasFactory;

    protected $table = 'salon_user';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'role' => SalonRole::class,
            'permissions' => AsEnumCollection::of(SalonPermission::class),
        ];
    }

    /**
     * @return BelongsTo<Salon, $this>
     */
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === SalonRole::Owner;
    }

    public function hasPermission(SalonPermission $permission): bool
    {
        return $this->isOwner() || ($this->permissions?->contains($permission) ?? false);
    }
}
