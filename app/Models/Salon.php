<?php

namespace App\Models;

use App\Domain\Schedule\TimeRange;
use App\Domain\Schedule\WeeklyHours;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\ReviewMode;
use App\Enums\SalonRole;
use App\Enums\SalonStatus;
use App\Enums\SalonTheme;
use App\Enums\ScheduleAccess;
use App\Support\BrandColor;
use Carbon\CarbonImmutable;
use Database\Factories\SalonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $description
 * @property string|null $logo_path
 * @property string|null $cover_path
 * @property string|null $brand_color
 * @property SalonTheme $theme
 * @property string|null $instagram
 * @property string|null $address
 * @property string|null $district
 * @property string|null $city
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string $timezone
 * @property string $currency
 * @property SalonStatus $status
 * @property bool $walk_ins_enabled The team keeps a day's line of people without a booking.
 * @property int $min_lead_minutes Notice a client must give before a booking starts.
 * @property int $max_days_ahead How far ahead clients can book.
 * @property int $slot_step_minutes Grid of the start times offered.
 * @property int $hold_minutes How long an unpaid deposit keeps the slot.
 * @property int $cancel_window_hours Cancelling later than this forfeits the deposit.
 * @property int $deposit_percent 0 = no deposit.
 * @property int|null $vip_deposit_percent Null = same as everyone.
 * @property string|null $payment_instructions
 * @property bool $accepts_online_bookings
 * @property ReviewMode $review_mode
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'name', 'business_type', 'clientele', 'phone', 'timezone', 'description', 'instagram',
    'address', 'district', 'city', 'latitude', 'longitude',
    'accepts_online_bookings', 'walk_ins_enabled', 'min_lead_minutes', 'max_days_ahead', 'slot_step_minutes',
    'cancel_window_hours', 'deposit_percent', 'vip_deposit_percent', 'hold_minutes', 'payment_instructions',
])]
class Salon extends Model
{
    /** @use HasFactory<SalonFactory> */
    use HasFactory, SoftDeletes;

    public const int TRIAL_DAYS = 30;

    /**
     * Mirrors the column defaults so fresh instances never miss them
     * (strict mode throws on missing attributes).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'description' => null,
        'logo_path' => null,
        'cover_path' => null,
        'brand_color' => null,
        'theme' => 'nocturna',
        'instagram' => null,
        'address' => null,
        'district' => null,
        'city' => null,
        'latitude' => null,
        'longitude' => null,
        'walk_ins_enabled' => false,
        'min_lead_minutes' => 60,
        'max_days_ahead' => 60,
        'slot_step_minutes' => 15,
        'hold_minutes' => 30,
        'cancel_window_hours' => 24,
        'deposit_percent' => 0,
        'vip_deposit_percent' => null,
        'payment_instructions' => null,
        'accepts_online_bookings' => true,
        'review_mode' => 'private',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'walk_ins_enabled' => 'boolean',
            'clientele' => Clientele::class,
            'status' => SalonStatus::class,
            'theme' => SalonTheme::class,
            'trial_ends_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accepts_online_bookings' => 'boolean',
            'review_mode' => ReviewMode::class,
        ];
    }

    /**
     * @return HasMany<SalonPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(SalonPhoto::class)->orderBy('position');
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * @return HasMany<Staff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class)->orderBy('position');
    }

    /**
     * @return HasMany<SalonHoliday, $this>
     */
    public function holidays(): HasMany
    {
        return $this->hasMany(SalonHoliday::class)->orderBy('date');
    }

    /**
     * When the salon attends the general public: the union of the hours of
     * its active professionals, without the ranges reserved for VIP clients
     * (a visitor could not book them). Names the salon explicitly because
     * visitors of the public page have no current salon.
     */
    public function openingHours(): WeeklyHours
    {
        $schedules = StaffSchedule::withoutGlobalScope('salon')
            ->where('salon_id', $this->id)
            ->where('access_level', '!=', ScheduleAccess::Vip)
            ->whereHas('staff', fn (Builder $staff) => $staff->withoutGlobalScope('salon')->where('is_active', true))
            ->get();

        return WeeklyHours::fromRanges($schedules->map(fn (StaffSchedule $schedule): array => [
            'weekday' => $schedule->weekday,
            'range' => TimeRange::fromStrings($schedule->start_time, $schedule->end_time),
        ]));
    }

    /**
     * The current time on the salon's clock.
     */
    public function localNow(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    /**
     * Share of the price asked in advance. VIP clients may have their own.
     */
    public function depositPercentFor(bool $isVip): int
    {
        return $isVip ? ($this->vip_deposit_percent ?? $this->deposit_percent) : $this->deposit_percent;
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Theme tokens for the salon brand, or null to use the platform's.
     *
     * @return array{light: array{primary: string, foreground: string}, dark: array{primary: string, foreground: string}, adjusted: bool}|null
     */
    public function brandTokens(): ?array
    {
        return $this->brand_color === null ? null : BrandColor::tokens($this->brand_color);
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

    /**
     * Whether the salon may change data. Past-due is the 7-day grace period
     * (PLAN §4.10) and still writable; suspended salons and expired trials
     * are read only until the subscription is renewed.
     */
    public function isWritable(): bool
    {
        return match ($this->status) {
            SalonStatus::Active, SalonStatus::PastDue => true,
            SalonStatus::Trial => $this->trial_ends_at === null || $this->trial_ends_at->isFuture(),
            SalonStatus::Suspended => false,
        };
    }

    public function ownersCount(): int
    {
        return $this->members()->where('role', SalonRole::Owner)->count();
    }
}
