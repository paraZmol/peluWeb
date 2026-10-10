<?php

namespace App\Domain\Accounting;

/**
 * What a professional earns for a completed visit. Pure: céntimos in,
 * céntimos out, integer arithmetic only.
 *
 * The discount is spread over the services in proportion to their price
 * (to the céntimo, adding up to exactly the discount), and each service then pays its own
 * rule on what is left: a percent, or a fixed amount capped at that base.
 */
final class CommissionCalculator
{
    /**
     * @param  list<array{priceCents: int, percent: int, fixedCents: int|null}>  $lines  one per service; a fixed amount wins over the percent
     */
    public static function forVisit(array $lines, int $discountCents): int
    {
        $total = array_sum(array_column($lines, 'priceCents'));
        $discount = min($discountCents, $total);
        $commission = 0;
        $priceSoFar = 0;
        $discountSoFar = 0;

        foreach ($lines as $line) {
            // Cumulative shares: each line takes what the running total owes
            // beyond what earlier lines took, so no line is discounted past
            // its own price and the last one lands on the exact discount.
            $priceSoFar += $line['priceCents'];
            $share = ($total === 0 ? 0 : intdiv($discount * $priceSoFar, $total)) - $discountSoFar;
            $discountSoFar += $share;
            $base = $line['priceCents'] - $share;

            $commission += $line['fixedCents'] !== null
                ? min($line['fixedCents'], $base)
                : self::percentOf($base, $line['percent']);
        }

        return $commission;
    }

    /**
     * Rounded half up to the céntimo.
     */
    private static function percentOf(int $cents, int $percent): int
    {
        return intdiv($cents * $percent + 50, 100);
    }
}
