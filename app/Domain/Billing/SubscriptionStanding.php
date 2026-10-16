<?php

namespace App\Domain\Billing;

use App\Enums\SubscriptionState;
use Carbon\CarbonImmutable;

/**
 * Where a salon stands, from its dates alone: the trial and what is paid
 * cover days; after the last covered day come a few days of grace, then
 * read only. Pure: today (the salon's local date) comes from outside, and
 * only the calendar day of each date counts.
 */
final readonly class SubscriptionStanding
{
    public function __construct(
        public SubscriptionState $state,
        /** Last day covered by the trial or a payment; null when nothing ever was. */
        public ?CarbonImmutable $coveredUntil,
        /** Whole days from today to the last covered day (0 on that day, negative after). */
        public ?int $daysLeft,
    ) {}

    /**
     * @param  CarbonImmutable|null  $trialEndsOn  last day of the trial
     * @param  CarbonImmutable|null  $paidUntil  last day paid for
     */
    public static function of(
        bool $isSuspended,
        ?CarbonImmutable $trialEndsOn,
        ?CarbonImmutable $paidUntil,
        CarbonImmutable $today,
        int $graceDays,
    ): self {
        // Calendar days only: a date column comes at midnight UTC and today in
        // the salon's zone; comparing their instants is off by a day.
        $today = self::day($today);
        $paidUntil = $paidUntil === null ? null : self::day($paidUntil);
        $coveredUntil = self::latest($trialEndsOn === null ? null : self::day($trialEndsOn), $paidUntil);
        $daysLeft = $coveredUntil === null ? null : (int) $today->diffInDays($coveredUntil, false);

        $state = match (true) {
            $isSuspended => SubscriptionState::Suspended,
            // Salons from before plans existed: nothing to count from.
            $coveredUntil === null, $paidUntil !== null && $paidUntil->gte($today) => SubscriptionState::Active,
            $coveredUntil->gte($today) => SubscriptionState::Trial,
            $coveredUntil->addDays($graceDays)->gte($today) => SubscriptionState::Grace,
            default => SubscriptionState::ReadOnly,
        };

        return new self($state, $coveredUntil, $daysLeft);
    }

    private static function day(CarbonImmutable $moment): CarbonImmutable
    {
        return CarbonImmutable::parse($moment->toDateString(), 'UTC');
    }

    private static function latest(?CarbonImmutable $a, ?CarbonImmutable $b): ?CarbonImmutable
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return $a->max($b);
    }
}
