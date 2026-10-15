<?php

namespace App\Domain\Promotions;

use App\Data\Booking\QuoteItem;
use Carbon\CarbonImmutable;

/**
 * One promotion per booking, and the client does not choose: among the
 * ones a visit qualifies for, the one that saves the most; on a tie, the
 * one that ends sooner.
 */
final class PromotionPicker
{
    /**
     * The promotions that would take something off this visit, best first.
     *
     * @param  list<PromotionTerms>  $promotions
     * @param  list<QuoteItem>  $items
     * @param  CarbonImmutable  $localStart  on the salon's clock
     * @return list<PromotionTerms>
     */
    public static function ranked(array $promotions, array $items, CarbonImmutable $localStart): array
    {
        $useful = array_filter(
            $promotions,
            fn (PromotionTerms $terms): bool => $terms->covers($localStart) && $terms->savedCents($items) > 0,
        );

        usort($useful, fn (PromotionTerms $a, PromotionTerms $b): int => [$b->savedCents($items), $a->endsOn, $a->id] <=> [$a->savedCents($items), $b->endsOn, $b->id]);

        return $useful;
    }
}
