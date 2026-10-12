<?php

namespace App\Actions\Promotions;

use App\Enums\PromotionStatus;
use App\Exceptions\PromotionException;
use App\Models\Promotion;
use App\Models\Salon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What the owner does with a promotion once it is written: publish it,
 * pause it, bring it back, copy it, or throw a draft away.
 */
class ChangePromotionStatus
{
    public function __construct(private readonly SavePromotion $save) {}

    /**
     * Publishes it, or brings a paused one back. A salon has a few at a
     * time: more than that and no client reads any.
     */
    public function publish(Salon $salon, Promotion $promotion): Promotion
    {
        return DB::transaction(function () use ($salon, $promotion): Promotion {
            // The salon's row: two "publish" at once count the same promotions.
            Salon::query()->whereKey($salon->id)->lockForUpdate()->first();
            $today = $salon->localNow()->toDateString();
            $promotion->load(['services', 'giftService']);

            if ($promotion->ends_on->toDateString() < $today) {
                throw PromotionException::alreadyEnded();
            }

            if (! $promotion->isIntact()) {
                throw PromotionException::needsReview();
            }

            $running = Promotion::query()
                ->where('status', PromotionStatus::Published)
                ->whereDate('ends_on', '>=', $today)
                ->whereKeyNot($promotion->id)
                ->count();

            if ($running >= Promotion::MAX_ACTIVE) {
                throw PromotionException::tooManyActive(Promotion::MAX_ACTIVE);
            }

            $promotion->status = PromotionStatus::Published;
            $promotion->published_at ??= CarbonImmutable::now();
            $promotion->save();

            return $promotion;
        });
    }

    public function pause(Promotion $promotion): Promotion
    {
        if ($promotion->status === PromotionStatus::Published) {
            $promotion->status = PromotionStatus::Paused;
            $promotion->save();
        }

        return $promotion;
    }

    /**
     * A copy as a draft, starting today and as long as the original: the
     * way to run it again, or to change what bookings locked.
     */
    public function duplicate(Salon $salon, Promotion $promotion): Promotion
    {
        $today = $salon->localNow()->startOfDay();
        $days = (int) $promotion->starts_on->diffInDays($promotion->ends_on);

        return DB::transaction(function () use ($promotion, $today, $days): Promotion {
            $copy = $promotion->replicate(['status', 'share_code', 'published_at', 'starts_on', 'ends_on']);
            $copy->status = PromotionStatus::Draft;
            $copy->starts_on = $today;
            $copy->ends_on = $today->addDays($days);
            $copy->share_code = $this->save->newShareCode();
            $copy->save();
            $copy->services()->sync($promotion->services()->pluck('services.id'));

            return $copy;
        });
    }

    public function delete(Promotion $promotion): void
    {
        if ($promotion->status !== PromotionStatus::Draft || $this->save->hasBookings($promotion)) {
            throw PromotionException::onlyDraftsAreDeleted();
        }

        $promotion->delete();
    }
}
