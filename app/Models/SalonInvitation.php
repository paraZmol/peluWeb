<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use Carbon\CarbonImmutable;
use Database\Factories\SalonInvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $salon_id
 * @property int|null $invited_by
 * @property string $email
 * @property SalonRole $role
 * @property Collection<int, SalonPermission>|null $permissions
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Salon|null $salon Null when the salon was soft-deleted.
 * @property-read User|null $inviter
 */
class SalonInvitation extends Model
{
    use BelongsToSalon;

    /** @use HasFactory<SalonInvitationFactory> */
    use HasFactory;

    public const int VALID_DAYS = 7;

    protected $hidden = ['token_hash'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'role' => SalonRole::class,
            'permissions' => AsEnumCollection::of(SalonPermission::class),
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Still usable: not accepted, not expired and its salon still exists.
     */
    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->expires_at->isFuture()
            && $this->salon !== null;
    }

    /**
     * @param  Builder<SalonInvitation>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }
}
