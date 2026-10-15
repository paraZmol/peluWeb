<?php

use App\Domain\Billing\SubscriptionStanding;
use App\Enums\SubscriptionState;
use Carbon\CarbonImmutable;

/*
| Step 7.3: where a salon stands, from its dates. The trial or a payment
| covers days; then 7 days of grace; then read only.
*/

function standingOn(string $today, ?string $trialEndsOn = null, ?string $paidUntil = null, bool $isSuspended = false): SubscriptionStanding
{
    return SubscriptionStanding::of(
        $isSuspended,
        $trialEndsOn === null ? null : CarbonImmutable::parse($trialEndsOn),
        $paidUntil === null ? null : CarbonImmutable::parse($paidUntil),
        CarbonImmutable::parse($today),
        7,
    );
}

it('follows the trial, its grace and then read only', function (string $today, SubscriptionState $state, int $daysLeft) {
    $standing = standingOn($today, trialEndsOn: '2026-11-14');

    expect($standing->state)->toBe($state)
        ->and($standing->daysLeft)->toBe($daysLeft)
        ->and($standing->coveredUntil?->toDateString())->toBe('2026-11-14');
})->with([
    'a week before the end' => ['2026-11-07', SubscriptionState::Trial, 7],
    'its last day' => ['2026-11-14', SubscriptionState::Trial, 0],
    'the day after' => ['2026-11-15', SubscriptionState::Grace, -1],
    'the last day of grace' => ['2026-11-21', SubscriptionState::Grace, -7],
    'after the grace' => ['2026-11-22', SubscriptionState::ReadOnly, -8],
]);

it('counts paid days on top of the trial', function () {
    expect(standingOn('2026-12-01', trialEndsOn: '2026-11-14', paidUntil: '2026-12-14'))
        ->state->toBe(SubscriptionState::Active)
        ->daysLeft->toBe(13);

    // Paid ahead while still in the trial: it is paid, not on trial.
    expect(standingOn('2026-11-01', trialEndsOn: '2026-11-14', paidUntil: '2026-12-14')->state)->toBe(SubscriptionState::Active);

    expect(standingOn('2026-12-20', trialEndsOn: '2026-11-14', paidUntil: '2026-12-14')->state)->toBe(SubscriptionState::Grace)
        ->and(standingOn('2026-12-22', trialEndsOn: '2026-11-14', paidUntil: '2026-12-14')->state)->toBe(SubscriptionState::ReadOnly);
});

it('keeps a suspension above any date', function () {
    expect(standingOn('2026-11-01', trialEndsOn: '2026-11-14', isSuspended: true)->state)->toBe(SubscriptionState::Suspended)
        ->and(SubscriptionState::Suspended->isWritable())->toBeFalse()
        ->and(SubscriptionState::Grace->isWritable())->toBeTrue();
});

it('leaves salons from before plans existed working', function () {
    expect(standingOn('2026-11-01'))
        ->state->toBe(SubscriptionState::Active)
        ->daysLeft->toBeNull();
});
