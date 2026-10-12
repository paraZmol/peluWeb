<?php

namespace App\Actions\Promotions;

use App\Domain\Promotions\PromotionCopy;
use App\Enums\BookingStatus;
use App\Enums\PromotionState;
use App\Enums\PromotionStatus;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Salon;
use App\Models\Service;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use stdClass;

/**
 * A salon's promotions as its owner sees them: each with where it stands
 * today, the words the client reads and what it has brought in so far.
 */
class SalonPromotions
{
    /** The newest ones: a salon runs a few at a time. */
    private const int LIMIT = 50;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Salon $salon): array
    {
        $today = $salon->localNow()->toDateString();
        $promotions = Promotion::withoutGlobalScope('salon')->where('salon_id', $salon->id)
            ->with(['services', 'giftService'])->latest('id')->limit(self::LIMIT)->get();
        $results = $this->results($promotions);

        return $promotions->map(fn (Promotion $promotion): array => $this->card($promotion, $salon, $today, $results->get($promotion->id)))->values()->all();
    }

    /**
     * Where it stands today: what the owner did with it, read against its
     * dates and its services.
     */
    public function state(Promotion $promotion, string $today): PromotionState
    {
        return match (true) {
            $promotion->status === PromotionStatus::Draft => PromotionState::Draft,
            $promotion->ends_on->toDateString() < $today => PromotionState::Ended,
            ! $promotion->isIntact() => PromotionState::NeedsReview,
            $promotion->status === PromotionStatus::Paused => PromotionState::Paused,
            $promotion->starts_on->toDateString() > $today => PromotionState::Scheduled,
            default => PromotionState::Active,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Promotion $promotion, Salon $salon, string $today, ?stdClass $result): array
    {
        $names = array_values($promotion->services->sortBy('position')->map(fn (Service $service): string => $service->name)->all());
        $state = $this->state($promotion, $today);
        $from = $promotion->from_minute;
        $clock = fn (?int $minute): ?string => $minute === null ? null : sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);

        return [
            'id' => $promotion->id,
            'card' => $promotion->card,
            'mechanic' => $promotion->mechanic->value,
            'name' => $promotion->name,
            'overline' => $promotion->overline,
            'state' => $state->value,
            'stateLabel' => $state->label(),
            'startsOn' => $promotion->starts_on->toDateString(),
            'endsOn' => $promotion->ends_on->toDateString(),
            'finePrint' => PromotionCopy::finePrint($promotion->terms(), $names, $promotion->quota, $promotion->once_per_client),
            'when' => PromotionCopy::when($promotion->terms()),
            'shareUrl' => route('salons.promotions.show', [$salon->slug, $promotion->share_code]),
            // Bookings were promised what it says: most of it can no longer change.
            'isLocked' => (int) ($result->bookings ?? 0) > 0,
            'form' => [
                'services' => $promotion->services->modelKeys(),
                'percent' => $promotion->percent,
                'amount' => $promotion->amount_cents === null ? null : Money::toAmount($promotion->amount_cents),
                'gift_service_id' => $promotion->gift_service_id,
                'weekdays' => $promotion->weekdays,
                'from_time' => $clock($from),
                'to_time' => $clock($promotion->to_minute),
                'quota' => $promotion->quota,
                'once_per_client' => $promotion->once_per_client,
            ],
            'results' => [
                'bookings' => (int) ($result->bookings ?? 0),
                'completed' => (int) ($result->completed ?? 0),
                'charged' => Money::toAmount((int) ($result->charged ?? 0)),
                'givenAway' => Money::toAmount((int) ($result->given_away ?? 0)),
                'placesUsed' => (int) ($result->bookings ?? 0),
            ],
        ];
    }

    /**
     * One query for all of them: bookings that hold a place, visits that
     * happened, what those paid and what the promotion took off them.
     *
     * @param  EloquentCollection<int, Promotion>  $promotions
     * @return Collection<int, stdClass>
     */
    private function results(EloquentCollection $promotions): Collection
    {
        $completed = BookingStatus::Completed->value;

        return Booking::withoutGlobalScope('salon')
            ->whereIn('promotion_id', $promotions->modelKeys())
            ->whereNotIn('status', [BookingStatus::Cancelled, BookingStatus::Expired])
            ->groupBy('promotion_id')
            ->selectRaw('promotion_id, COUNT(*) as bookings')
            ->selectRaw('SUM(status = ?) as completed', [$completed])
            ->selectRaw('SUM(CASE WHEN status = ? THEN COALESCE(charged_cents, 0) ELSE 0 END) as charged', [$completed])
            ->selectRaw('SUM(CASE WHEN status = ? THEN promotion_cents ELSE 0 END) as given_away', [$completed])
            ->toBase()
            ->get()
            ->keyBy('promotion_id');
    }
}
