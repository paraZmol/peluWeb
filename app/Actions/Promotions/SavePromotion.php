<?php

namespace App\Actions\Promotions;

use App\Data\Promotions\PromotionDraft;
use App\Enums\BookingStatus;
use App\Enums\PromotionStatus;
use App\Exceptions\PromotionException;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Salon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes a promotion from its form: a new draft, or changes to one. Once
 * it has bookings their price is frozen on what it said, so only what
 * cannot contradict them may change: a later end, more places, the title.
 */
class SavePromotion
{
    private const int SHARE_CODE_LENGTH = 10;

    public function handle(Salon $salon, PromotionDraft $draft, ?Promotion $promotion = null): Promotion
    {
        return DB::transaction(function () use ($salon, $draft, $promotion): Promotion {
            if ($promotion === null) {
                $promotion = new Promotion;
                $promotion->salon_id = $salon->id;
                $promotion->status = PromotionStatus::Draft;
                $promotion->share_code = $this->newShareCode();
            } else {
                // Locked first: a booking being made right now is either
                // counted here or waits for these changes.
                $promotion = Promotion::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($promotion->id);

                if ($this->hasBookings($promotion)) {
                    return $this->amend($promotion, $draft);
                }
            }

            $promotion->forceFill([
                'card' => $draft->card,
                'mechanic' => $draft->mechanic,
                'starts_on' => $draft->startsOn,
                'ends_on' => $draft->endsOn,
                'weekdays' => $draft->weekdays,
                'from_minute' => $draft->fromMinute,
                'to_minute' => $draft->toMinute,
                'percent' => $draft->percent,
                'amount_cents' => $draft->amountCents,
                'gift_service_id' => $draft->giftServiceId,
                'quota' => $draft->quota,
                'once_per_client' => $draft->oncePerClient,
                'new_clients_only' => $draft->newClientsOnly,
                'overline' => $draft->overline,
                // Filled below, once its services are known.
                'name' => $draft->name ?? '',
            ])->save();
            $promotion->services()->sync($draft->serviceIds);

            if ($draft->name === null) {
                $promotion->name = $this->title($promotion->refresh());
                $promotion->save();
            }

            return $promotion;
        });
    }

    public function hasBookings(Promotion $promotion): bool
    {
        // A cancelled booking, or one whose hold ran out, was promised nothing.
        return Booking::withoutGlobalScope('salon')->where('promotion_id', $promotion->id)
            ->whereNotIn('status', [BookingStatus::Cancelled, BookingStatus::Expired])->sharedLock()->exists();
    }

    /**
     * The title the system writes: its services by name, in catalogue order.
     * Stored without a combo's price, which changes with the catalogue.
     */
    public function title(Promotion $promotion): string
    {
        $promotion->load(['services.variants', 'giftService']);

        return $promotion->writtenTitle(withPrice: false);
    }

    /**
     * With bookings made: anything that would change what they were
     * promised is refused, whatever else came in the form.
     */
    private function amend(Promotion $promotion, PromotionDraft $draft): Promotion
    {
        $shrinksPlaces = $promotion->quota !== null && $draft->quota !== null && $draft->quota < $promotion->quota;
        $addsLimit = $promotion->quota === null && $draft->quota !== null;

        if ($draft->endsOn < $promotion->ends_on->toDateString() || $shrinksPlaces || $addsLimit || ! $this->keepsTerms($promotion, $draft)) {
            throw PromotionException::lockedByBookings();
        }

        $promotion->ends_on = CarbonImmutable::parse($draft->endsOn);
        $promotion->quota = $draft->quota;
        $promotion->name = $draft->name ?? $promotion->name;
        $promotion->save();

        return $promotion;
    }

    private function keepsTerms(Promotion $promotion, PromotionDraft $draft): bool
    {
        $services = $promotion->services()->pluck('services.id')->sort()->values()->all();
        $asked = collect($draft->serviceIds)->sort()->values()->all();

        return $services === $asked
            && $promotion->mechanic === $draft->mechanic
            && $promotion->starts_on->toDateString() === $draft->startsOn
            && $promotion->weekdays === $draft->weekdays
            && [$promotion->from_minute, $promotion->to_minute] === [$draft->fromMinute, $draft->toMinute]
            && [$promotion->percent, $promotion->amount_cents, $promotion->gift_service_id] === [$draft->percent, $draft->amountCents, $draft->giftServiceId]
            && $promotion->once_per_client === $draft->oncePerClient
            && $promotion->new_clients_only === $draft->newClientsOnly;
    }

    public function newShareCode(): string
    {
        do {
            $code = Str::lower(Str::random(self::SHARE_CODE_LENGTH));
        } while (Promotion::withoutGlobalScope('salon')->withTrashed()->where('share_code', $code)->exists());

        return $code;
    }
}
