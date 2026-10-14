<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Domain\Promotions\ComboPrice;
use App\Domain\Promotions\PromotionCopy;
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
use Illuminate\Support\Facades\DB;

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
 * @property-read Collection<int, Staff> $staff
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
     * The professionals it holds with; none = all of the team.
     *
     * @return BelongsToMany<Staff, $this>
     */
    public function staff(): BelongsToMany
    {
        // Removed ones too: losing its only professional must not open it
        // to the whole team.
        return $this->belongsToMany(Staff::class)->withoutGlobalScope('salon')->withTrashed();
    }

    /**
     * The ones of its professionals who can still be chosen, in the
     * team's order. Empty for a promotion of all of the team.
     *
     * @return Collection<int, Staff>
     */
    public function bookableStaff(): Collection
    {
        return $this->staff->filter(fn (Staff $person): bool => $person->is_active && ! $person->trashed())->sortBy('position')->values();
    }

    /**
     * @return list<int>|null null when it holds with all of the team
     */
    public function staffIds(): ?array
    {
        $ids = $this->relationLoaded('staff') ? $this->staff->modelKeys() : $this->staff()->pluck('staff.id')->all();

        $ids = array_map(intval(...), $ids);
        sort($ids);

        return $ids === [] ? null : $ids;
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function giftService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'gift_service_id')->withoutGlobalScope('salon')->withTrashed();
    }

    /**
     * Every service it names is still on offer, and so is one of its
     * professionals when it has some. When one is switched off the
     * promotion stops applying until the owner looks at it.
     */
    public function isIntact(): bool
    {
        $isOffered = fn (?Service $service): bool => $service !== null && $service->is_active && ! $service->trashed();

        return $this->services->isNotEmpty()
            && $this->services->every($isOffered)
            && ($this->mechanic !== PromotionMechanic::Gift || $isOffered($this->giftService))
            // Only with some of the team: one of them must still be there.
            && ($this->staffIds() === null || $this->bookableStaff()->isNotEmpty());
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
            staffNames: array_values($this->bookableStaff()->pluck('name')->all()),
        );
    }

    /**
     * The title the system writes for it, from its services as they are now.
     * $withPrice: a combo's live price ("Corte + Barba por S/ 45"); without
     * it, the name it is stored under ("Combo Corte + Barba").
     */
    public function writtenTitle(bool $withPrice = true): string
    {
        $services = $this->services->sortBy('position')->values();

        return PromotionCopy::title(
            $this->terms(),
            array_values($services->map(fn (Service $service): string => $service->name)->all()),
            $this->giftService?->name,
            $withPrice ? $this->comboPrice() : null,
        );
    }

    /**
     * What a combo is announced at; null for any other promotion. "Desde"
     * when an option or a professional's own price may change it.
     */
    public function comboPrice(): ?ComboPrice
    {
        return $this->mechanic === PromotionMechanic::Combo ? self::priceCombo($this->services, (int) $this->amount_cents, $this->staffIds()) : null;
    }

    /**
     * These services together, as the cheapest active professional who
     * does them all charges them (the cheapest option of each), so what is
     * announced can be booked. Not bookable when nobody does them all.
     *
     * @param  Collection<int, Service>  $services
     * @param  list<int>|null  $staffIds  only with these professionals; null = all of the team
     */
    public static function priceCombo(Collection $services, int $savingCents, ?array $staffIds = null): ComboPrice
    {
        $services->loadMissing('variants');
        $byStaff = DB::table('service_staff')
            ->join('staff', 'staff.id', '=', 'service_staff.staff_id')
            ->whereIn('service_staff.service_id', $services->modelKeys())
            ->when($staffIds !== null, fn ($query) => $query->whereIn('service_staff.staff_id', (array) $staffIds))
            ->where('staff.is_active', true)
            ->whereNull('staff.deleted_at')
            ->get(['service_staff.staff_id', 'service_staff.service_id', 'service_staff.price_cents'])
            ->groupBy('staff_id')
            ->filter(fn ($rows): bool => $rows->count() === $services->count());
        // An option's price is the same whoever does it; otherwise their own price counts.
        $totals = $byStaff->map(fn ($rows): int => (int) $services->sum(fn (Service $service): int => $service->variants->isNotEmpty()
            ? $service->basePriceCents()
            : (int) ($rows->firstWhere('service_id', $service->id)->price_cents ?? $service->price_cents)));

        return new ComboPrice(
            normalCents: (int) ($totals->min() ?? $services->sum(fn (Service $service): int => $service->basePriceCents())),
            savingCents: $savingCents,
            isFrom: $totals->unique()->count() > 1 || $services->contains(fn (Service $service): bool => $service->variants->isNotEmpty()),
            isBookable: $totals->isNotEmpty(),
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
            staffIds: $this->staffIds(),
        );
    }
}
