<?php

namespace App\Models;

use App\Support\Images\ImageStore;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $avatar_path
 * @property string|null $google_id
 * @property bool $is_platform_admin
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, SalonMember> $memberships
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'google_id', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Mirrors the column defaults so a freshly created instance is never
     * missing these attributes (strict mode would throw on access).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'phone' => null,
        'avatar_path' => null,
        'google_id' => null,
        'is_platform_admin' => false,
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['avatar'];

    /**
     * Public URL of the profile photo, used by the frontend avatar.
     *
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn (): ?string => app(ImageStore::class)->url($this->avatar_path));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SalonMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(SalonMember::class);
    }

    public function membershipIn(Salon|int $salon): ?SalonMember
    {
        $salonId = $salon instanceof Salon ? $salon->id : $salon;

        return $this->memberships->firstWhere('salon_id', $salonId);
    }

    /**
     * Only the platform owner reaches /plataforma; 2FA is enforced by the
     * RequireTwoFactor middleware so the admin gets a helpful redirect.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_platform_admin && $this->hasVerifiedEmail();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }
}
