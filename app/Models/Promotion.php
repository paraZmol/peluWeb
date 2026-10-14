<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Domain\Promotions\PromotionFacts;
use App\Domain\Promotions\PromotionTerms;
use App\Enums\PromotionMechanic;
use App\Enums\PromotionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A promotion a salon publishes: it applies by itself to the bookings
 * that qualify, with no codes (docs/promociones.md).
 *
 * @property int $id
 * @property int $salon_id
 * @property PromotionMechanic $mechanic
 * @property string $card The card the owner started from.
 * @property string $name
 * @property string|null $overline
 * @property PromotionStatus $status
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property list<int>|null $weekdays
 * @property int|null $from_minute
 * @property int|null $to_minute
 * @property int|null $percent
 * @property int|null $amount_cents
 * @property int|null $gift_service_id
 * @property int|null $quota
 * @property bool $once_per_client
 * @property bool $new_clients_only Only for someone who never had a booking at the salon.
 * @property string $audience
 * @property string $share_code
 * @property CarbonImmutable|null $published_at
 * @property-read Collection<int, Service> $services
 * @property-read Service|null $giftService
 */
class Promotion extends Model
{
    use BelongsToSalon, SoftDeletes;

    /** How long a promotion may run at most. */
    public const int MAX_DAYS = 90;

    /** Promotions a salon may have published at once. */
    public const int MAX_ACTIVE = 3;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mechanic' => PromotionMechanic::class,
            'status' => PromotionStatus::class,
            'starts_on' => 'immutable_date:Y-m-d',
            'ends_on' => 'immutable_date:Y-m-d',
            'weekdays' => 'array',
            'once_per_client' => 'boolean',
            'new_clients_only' => 'boolean',
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * Published and with a visit on that day inside its dates.
     *
     * @param  Builder<self>  $query
     * @param  string  $day  "Y-m-d" on the salon's calendar
     */
    public function scopeRunningOn(Builder $query, string $day): void
    {
        $query->where('status', PromotionStatus::Published)->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day);
    }

    /**
     * The services it applies to. Without the salon scope: the promotion
     * is already of one salon, and bookings are priced with none current.
     *
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        // Deleted ones too: a promotion that lost a service must notice it.
        return $this->belongsToMany(Service::class)->withoutGlobalScope('salon')->withTrashed();
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function giftService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'gift_service_id')->withoutGlobalScope('salon')->withTrashed();
    }

    /**
     * Every service it names is still on offer. When one is switched off
     * the promotion stops applying until the owner looks at it.
     */
    public function isIntact(): bool
    {
        $isOffered = fn (?Service $service): bool => $service !== null && $service->is_active && ! $service->trashed();

        return $this->services->isNotEmpty()
            && $this->services->every($isOffered)
            && ($this->mechanic !== PromotionMechanic::Gift || $isOffered($this->giftService));
    }

    /**
     * What its fine print says besides the terms. Takes the salon: it is
     * also read where none is current.
     */
    public function facts(Salon $salon): PromotionFacts
    {
        $gift = $this->giftService;

        return new PromotionFacts(
            salonName: $salon->name,
            quota: $this->quota,
            oncePerClient: $this->once_per_client,
            giftName: $gift?->name,
            giftPriceCents: $gift?->price_cents,
            giftMinutes: $gift === null ? null : $gift->active_minutes + $gift->idle_minutes + $gift->finish_minutes,
            hasDeposit: $salon->deposit_percent > 0,
        );
    }

    public function terms(): PromotionTerms
    {
        return new PromotionTerms(
            id: $this->id,
            name: $this->name,
            mechanic: $this->mechanic,
            serviceIds: array_values($this->services->modelKeys()),
            startsOn: $this->starts_on->toDateString(),
            endsOn: $this->ends_on->toDateString(),
            weekdays: $this->weekdays,
            fromMinute: $this->from_minute,
            toMinute: $this->to_minute,
            percent: $this->percent,
            amountCents: $this->amount_cents,
            giftServiceId: $this->gift_service_id,
            newClientsOnly: $this->new_clients_only,
        );
    }
}
