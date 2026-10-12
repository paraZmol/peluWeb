<?php

namespace App\Actions\Promotions;

use App\Data\Booking\BookingQuote;
use App\Domain\Promotions\PromotionPicker;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Salon;
use App\Models\SalonClient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Prices a quote under the salon's best promotion for that visit, if any
 * applies and still has room. Names the salon explicitly: visitors booking
 * on the public page have no current salon.
 *
 * With $underLock it runs inside the booking's transaction: the promotion
 * row is locked before its places are counted, so two bookings cannot
 * take the last one. Lock order: professional → client → booking →
 * promotion → salon.
 */
class ApplyPromotion
{
    /**
     * @param  SalonClient|null  $client  null, or new and unsaved, for someone who never booked here
     */
    public function handle(Salon $salon, ?SalonClient $client, BookingQuote $quote, CarbonImmutable $startsAt, bool $underLock = false): BookingQuote
    {
        $localStart = $startsAt->setTimezone($salon->timezone);
        $running = Promotion::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->runningOn($localStart->toDateString())
            ->with(['services', 'giftService'])
            ->get()
            ->filter(fn (Promotion $promotion): bool => $promotion->isIntact())
            ->keyBy('id');

        if ($running->isEmpty()) {
            return $quote;
        }

        $ranked = PromotionPicker::ranked(
            array_values($running->map(fn (Promotion $promotion) => $promotion->terms())->all()),
            $quote->items,
            $localStart,
        );

        foreach ($ranked as $terms) {
            /** @var Promotion $promotion */
            $promotion = $running->get($terms->id);

            if ($this->hasRoom($promotion, $client, $underLock)) {
                return $quote->withPromotion($terms);
            }
        }

        return $quote;
    }

    /**
     * Places left, and not already used by this client when it is one
     * each. A booking that was cancelled or whose hold ran out gives its
     * place back; a visit that happened, or was missed, keeps it.
     */
    private function hasRoom(Promotion $promotion, ?SalonClient $client, bool $underLock): bool
    {
        if ($promotion->quota === null && ! $promotion->once_per_client) {
            return true;
        }

        if ($underLock) {
            Promotion::withoutGlobalScope('salon')->whereKey($promotion->id)->lockForUpdate()->first();
        }

        $taken = Booking::withoutGlobalScope('salon')
            ->where('promotion_id', $promotion->id)
            ->whereNotIn('status', [BookingStatus::Cancelled, BookingStatus::Expired])
            ->where(fn (Builder $live) => $live
                ->where('status', '!=', BookingStatus::Pending)
                ->orWhereNull('hold_expires_at')
                ->orWhere('hold_expires_at', '>', CarbonImmutable::now()));

        if ($promotion->quota !== null && (clone $taken)->count() >= $promotion->quota) {
            return false;
        }

        return ! $promotion->once_per_client
            || $client === null
            || ! $client->exists
            || ! $taken->where('salon_client_id', $client->id)->exists();
    }
}
