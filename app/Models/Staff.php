<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Support\Images\ImageStore;
use App\Support\PersonName;
use Carbon\CarbonImmutable;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A professional who attends clients. The agenda is per professional.
 * May have no user account (`user_id` null): the owner manages their hours.
 *
 * @property int $id
 * @property int $salon_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $title
 * @property string|null $photo_path
 * @property bool $is_active
 * @property int $position
 * @property int $commission_percent Share of each service, unless the service sets its own rule.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read User|null $user
 * @property-read Collection<int, StaffSchedule> $schedules
 * @property-read Collection<int, StaffTimeOff> $timeOff
 * @property-read Collection<int, Service> $services
 */
#[Fillable(['name', 'title', 'is_active'])]
class Staff extends Model
{
    use BelongsToSalon;

    /** @use HasFactory<StaffFactory> */
    use HasFactory, SoftDeletes;

    public const int MAX_PER_SALON = 50;

    public const int PHOTO_SIZE = 512;

    public const int NAME_MAX_LENGTH = 80;

    protected $table = 'staff';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'user_id' => null,
        'title' => null,
        'photo_path' => null,
        'is_active' => true,
        'position' => 0,
        'commission_percent' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * A professional's name taken from an account name: account names are
     * longer and may carry characters that salon texts never allow.
     */
    public static function nameFrom(string $accountName): string
    {
        return PersonName::clean($accountName, self::NAME_MAX_LENGTH, 'Profesional');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<StaffSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(StaffSchedule::class)->orderBy('weekday')->orderBy('start_time');
    }

    /**
     * @return HasMany<StaffTimeOff, $this>
     */
    public function timeOff(): HasMany
    {
        return $this->hasMany(StaffTimeOff::class)->orderBy('starts_at');
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->withPivot(['active_minutes', 'price_cents', 'commission_percent', 'commission_cents'])
            ->withTimestamps();
    }

    /**
     * Their own photo, or the profile photo of the linked account.
     */
    public function photoUrl(): ?string
    {
        return app(ImageStore::class)->url($this->photo_path ?? $this->user?->avatar_path);
    }
}
