<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\ClientLevel;
use Carbon\CarbonImmutable;
use Database\Factories\SalonClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person as one salon knows them. The same user is a separate client in
 * each salon: being VIP in one says nothing about another.
 *
 * Nothing is mass assignable: VIP is only written by MarkClientVip.
 *
 * @property int $id
 * @property int $salon_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $phone
 * @property bool $is_vip
 * @property int $no_show_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 */
class SalonClient extends Model
{
    use BelongsToSalon;

    /** @use HasFactory<SalonClientFactory> */
    use HasFactory;

    public const int NAME_MAX_LENGTH = 80;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'user_id' => null,
        'phone' => null,
        'is_vip' => false,
        'no_show_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_vip' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function level(): ClientLevel
    {
        return $this->is_vip ? ClientLevel::Vip : ClientLevel::Standard;
    }
}
