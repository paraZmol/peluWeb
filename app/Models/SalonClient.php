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
 * Nothing is mass assignable: VIP is only written by Owner\ClientController.
 *
 * @property int $id
 * @property int $salon_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $phone
 * @property bool $is_vip
 * @property int $no_show_count
 * @property string|null $notes How the person likes to be attended.
 * @property string|null $formula_notes Products, tones and times of their usual work.
 * @property string|null $care_notes Allergies and reactions: health data, only with written consent on record.
 * @property CarbonImmutable|null $care_consent_at
 * @property int|null $care_consent_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read User|null $careConsentBy
 */
class SalonClient extends Model
{
    use BelongsToSalon;

    /** @use HasFactory<SalonClientFactory> */
    use HasFactory;

    public const int NAME_MAX_LENGTH = 80;

    public const int NOTES_MAX_LENGTH = 1000;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'user_id' => null,
        'phone' => null,
        'is_vip' => false,
        'no_show_count' => 0,
        'notes' => null,
        'formula_notes' => null,
        'care_notes' => null,
        'care_consent_at' => null,
        'care_consent_by' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_vip' => 'boolean', 'care_consent_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who recorded that the person authorized keeping their care notes.
     *
     * @return BelongsTo<User, $this>
     */
    public function careConsentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'care_consent_by');
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
