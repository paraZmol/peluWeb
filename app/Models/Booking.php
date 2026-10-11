<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Domain\Booking\BookingShape;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A visit of one client with one professional. Instants are UTC; prices
 * and durations are frozen in `booking_services` when it is created.
 *
 * Nothing is mass assignable: bookings are only written by the Actions in
 * App\Actions\Booking, which hold the lock that prevents double booking.
 *
 * @property int $id
 * @property int $salon_id
 * @property string $code
 * @property int $salon_client_id
 * @property int $staff_id
 * @property BookingStatus $status
 * @property BookingSource $source
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property bool $is_vip
 * @property int $price_cents
 * @property int $deposit_cents
 * @property CarbonImmutable|null $deposit_paid_at
 * @property bool|null $deposit_refundable
 * @property CarbonImmutable|null $deposit_refunded_at
 * @property int $discount_cents
 * @property int|null $charged_cents What the visit finally cost; null until it is completed.
 * @property int|null $commission_cents What whoever attended earns for it; frozen when it is completed.
 * @property int|null $commission_settlement_id Set once that commission was paid.
 * @property CarbonImmutable|null $hold_expires_at
 * @property CarbonImmutable|null $reminder_sent_at
 * @property string|null $client_note
 * @property string|null $reference_photo_path
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SalonClient $client
 * @property-read Staff $staff Also when the professional was removed later.
 * @property-read Collection<int, BookingService> $services
 * @property-read Collection<int, BookingSegment> $segments
 * @property-read Collection<int, BookingEvent> $events
 * @property-read Collection<int, Payment> $payments
 * @property-read Review|null $review
 */
class Booking extends Model
{
    use BelongsToSalon;

    public const int CODE_LENGTH = 8;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_vip' => false,
        'deposit_cents' => 0,
        'deposit_paid_at' => null,
        'deposit_refundable' => null,
        'deposit_refunded_at' => null,
        'discount_cents' => 0,
        'charged_cents' => null,
        'commission_cents' => null,
        'commission_settlement_id' => null,
        'hold_expires_at' => null,
        'client_note' => null,
        'reference_photo_path' => null,
        'created_by' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'source' => BookingSource::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'deposit_paid_at' => 'datetime',
            'deposit_refunded_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'is_vip' => 'boolean',
            'deposit_refundable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SalonClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(SalonClient::class, 'salon_client_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }

    /**
     * What the client said about this visit, if anything.
     *
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * @return HasMany<BookingService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(BookingService::class)->orderBy('position');
    }

    /**
     * @return HasMany<BookingSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(BookingSegment::class)->orderBy('starts_at');
    }

    /**
     * @return HasMany<BookingEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class)->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * Bookings that hold their professional's time right now: confirmed,
     * in service, or pending with a hold that has not run out. An expired
     * hold frees the slot by itself; no scheduled task is needed for that.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeHoldingSlot(Builder $query, ?CarbonImmutable $now = null): void
    {
        $query->where(fn (Builder $holding) => $holding
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::InService])
            ->orWhere(fn (Builder $pending) => $pending
                ->where('status', BookingStatus::Pending)
                ->where('hold_expires_at', '>', $now ?? now())));
    }

    /**
     * Pending and past its hold: it no longer holds the slot, whatever the
     * stored status still says.
     */
    public function isHoldExpired(?CarbonImmutable $now = null): bool
    {
        return $this->status === BookingStatus::Pending
            && $this->hold_expires_at !== null
            && $this->hold_expires_at->lessThanOrEqualTo($now ?? now());
    }

    /**
     * The status people should see: an expired hold reads as expired even
     * before the scheduled task records it.
     */
    public function effectiveStatus(?CarbonImmutable $now = null): BookingStatus
    {
        return $this->isHoldExpired($now) ? BookingStatus::Expired : $this->status;
    }

    /**
     * How the visit takes its professional's time, from the durations
     * frozen when it was booked.
     */
    public function shape(): BookingShape
    {
        $durations = [];

        foreach ($this->services()->get() as $service) {
            $durations[] = [
                'active' => $service->active_minutes,
                'idle' => $service->idle_minutes,
                'finish' => $service->finish_minutes,
            ];
        }

        return BookingShape::fromServices($durations);
    }

    public function needsDeposit(): bool
    {
        return $this->deposit_cents > 0 && $this->deposit_paid_at === null;
    }

    /**
     * What the client already handed over as a deposit and still counts
     * toward the visit.
     */
    public function depositPaidCents(): int
    {
        return $this->deposit_paid_at !== null && $this->deposit_refunded_at === null ? $this->deposit_cents : 0;
    }

    /**
     * A paid deposit of a cancelled visit that the salon still owes back.
     */
    public function canRefundDeposit(): bool
    {
        return $this->status === BookingStatus::Cancelled
            && $this->deposit_paid_at !== null
            && $this->deposit_refundable === true
            && $this->deposit_refunded_at === null;
    }
}
