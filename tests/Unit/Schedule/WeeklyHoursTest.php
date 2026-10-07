<?php

use App\Domain\Schedule\TimeRange;
use App\Domain\Schedule\WeeklyHours;
use Carbon\CarbonImmutable;

/**
 * @param  list<array{int, string, string}>  $entries  weekday, start, end
 */
function weeklyHours(array $entries): WeeklyHours
{
    return WeeklyHours::fromRanges(array_map(
        fn (array $entry): array => ['weekday' => $entry[0], 'range' => TimeRange::fromStrings($entry[1], $entry[2])],
        $entries,
    ));
}

// 2026-10-07 is a Wednesday (ISO weekday 3).
function wednesdayAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2026-10-07 {$time}", 'America/Lima');
}

it('treats ranges that only touch as not overlapping', function () {
    $morning = TimeRange::fromStrings('09:00', '13:00');

    expect($morning->overlaps(TimeRange::fromStrings('13:00', '18:00')))->toBeFalse()
        ->and($morning->overlaps(TimeRange::fromStrings('12:59', '18:00')))->toBeTrue()
        ->and($morning->overlaps(TimeRange::fromStrings('10:00', '11:00')))->toBeTrue();
});

it('rejects a range that ends before it starts', function () {
    TimeRange::fromStrings('18:00', '09:00');
})->throws(InvalidArgumentException::class);

it('reads times with seconds as stored by MySQL', function () {
    expect(TimeRange::fromStrings('09:30:00', '18:00:00')->start())->toBe('09:30');
});

it('joins the hours of several professionals into the salon hours', function () {
    $hours = weeklyHours([
        [3, '15:00', '20:00'],
        [3, '09:00', '13:00'],
        [3, '12:00', '14:00'],
        [3, '14:00', '15:00'],
    ]);

    $wednesday = $hours->days()[3];

    expect($wednesday)->toHaveCount(1)
        ->and($wednesday[0]->start())->toBe('09:00')
        ->and($wednesday[0]->end())->toBe('20:00')
        ->and($hours->days()[4])->toBe([]);
});

it('keeps a lunch break as two separate ranges', function () {
    $hours = weeklyHours([[3, '09:00', '13:00'], [3, '15:00', '20:00']]);

    expect($hours->days()[3])->toHaveCount(2);
});

it('knows when there are no hours at all', function () {
    expect(weeklyHours([])->isEmpty())->toBeTrue()
        ->and(weeklyHours([[1, '09:00', '10:00']])->isEmpty())->toBeFalse();
});

it('is open inside a range and tells when it closes', function () {
    $status = weeklyHours([[3, '09:00', '13:00']])->statusAt(wednesdayAt('12:59'));

    expect($status->isOpen)->toBeTrue()->and($status->time)->toBe('13:00');
});

it('is closed exactly at closing time and points to the next range today', function () {
    $status = weeklyHours([[3, '09:00', '13:00'], [3, '15:00', '20:00']])->statusAt(wednesdayAt('13:00'));

    expect($status->isOpen)->toBeFalse()
        ->and($status->time)->toBe('15:00')
        ->and($status->daysAhead)->toBe(0);
});

it('points to tomorrow after closing', function () {
    $status = weeklyHours([[3, '09:00', '13:00'], [4, '10:00', '18:00']])->statusAt(wednesdayAt('21:00'));

    expect($status->isOpen)->toBeFalse()
        ->and($status->time)->toBe('10:00')
        ->and($status->daysAhead)->toBe(1)
        ->and($status->weekday)->toBe(4);
});

it('skips days off until the next working day', function () {
    $status = weeklyHours([[1, '09:00', '13:00']])->statusAt(wednesdayAt('10:00'));

    expect($status->daysAhead)->toBe(5)->and($status->weekday)->toBe(1);
});

it('is closed on a holiday even inside working hours', function () {
    $hours = weeklyHours([[3, '09:00', '18:00'], [4, '09:00', '18:00']]);

    $status = $hours->statusAt(wednesdayAt('10:00'), ['2026-10-07']);

    expect($status->isOpen)->toBeFalse()->and($status->daysAhead)->toBe(1);
});

it('skips holidays when looking for the next opening', function () {
    $hours = weeklyHours([[3, '09:00', '18:00'], [4, '09:00', '18:00'], [5, '11:00', '18:00']]);

    $status = $hours->statusAt(wednesdayAt('19:00'), ['2026-10-08']);

    expect($status->weekday)->toBe(5)->and($status->time)->toBe('11:00');
});

it('gives no next opening when the salon never opens', function () {
    $status = weeklyHours([])->statusAt(wednesdayAt('10:00'));

    expect($status->isOpen)->toBeFalse()->and($status->time)->toBeNull();
});
