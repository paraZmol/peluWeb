<?php

namespace App\Models;

use App\Concerns\BelongsToSalon;
use App\Enums\WaitlistStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A place on a salon's waiting list: what the person wants, with whom and
 * on which day. Told one at a time when a time opens up.
 *
 * @property int $id
 * @property int $salon_id
 * @property int $salon_client_id
 * @property int|null $staff_id
 * @property CarbonImmutable $date
 * @property list<array{service_id: int, variant_id: int|null}> $selections
 * @property WaitlistStatus $status
 * @property int $offers_count
 * @property CarbonImmutable|null $offered_at
 * @property CarbonImmutable|null $offer_expires_at
 * @property CarbonImmutable|null $created_at
 * @property-read SalonClient $client
 */
class WaitlistEntry extends Model
{
    use BelongsToSalon;

    /** Places one person may hold in a salon at once. */
    public const int MAX_PER_CLIENT = 3;

    /** How long a freed time is theirs to take before the next person is told. */
    public const int OFFER_MINUTES = 30;

    /** Turns someone may let go by before their place is closed. */
    public const int MAX_OFFERS = 3;

    /** People one salon's list takes for one day. */
    public const int MAX_PER_DAY = 30;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date:Y-m-d',
            'selections' => 'array',
            'status' => WaitlistStatus::class,
            'offered_at' => 'immutable_datetime',
            'offer_expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [WaitlistStatus::Waiting, WaitlistStatus::Offered]);
    }

    /**
     * @return BelongsTo<SalonClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(SalonClient::class, 'salon_client_id')->withoutGlobalScope('salon');
    }
}
