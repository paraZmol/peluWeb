<?php

use App\Domain\Booking\AvailabilityCalculator;
use App\Domain\Booking\BookingShape;
use App\Domain\Booking\TimeSpan;
use App\Domain\Booking\WorkingWindows;
use App\Enums\ScheduleAccess;
use Carbon\CarbonImmutable;

/*
| The invariants of the `booking-domain` skill, on the pure calculator.
| Wednesday 2026-10-07 in Lima (UTC-5) is the reference day.
*/

function at(string $time, string $date = '2026-10-07'): int
{
    return CarbonImmutable::parse("{$date} {$time}", 'America/Lima')->getTimestamp();
}

function span(string $from, string $to, string $date = '2026-10-07'): TimeSpan
{
    return new TimeSpan(at($from, $date), at($to, $date));
}

/**
 * @param  list<array{int, int, int}>  $services  active, idle, finish
 */
function shape(array ...$services): BookingShape
{
    return BookingShape::fromServices(array_map(
        fn (array $s): array => ['active' => $s[0], 'idle' => $s[1] ?? 0, 'finish' => $s[2] ?? 0],
        $services,
    ));
}

/**
 * @param  list<int>  $slots
 * @return list<string>
 */
function asTimes(array $slots): array
{
    return array_map(fn (int $slot): string => CarbonImmutable::createFromTimestamp($slot, 'America/Lima')->format('H:i'), $slots);
}

function slotsFor(array $windows, array $busy, BookingShape $shape, int $step = 15, ?int $earliest = null, ?int $latest = null): array
{
    return asTimes((new AvailabilityCalculator)->slots($windows, $busy, $shape, $step, $earliest ?? at('00:00'), $latest ?? at('23:59')));
}

describe('shape of a visit', function () {
    it('is one busy stretch for a plain service', function () {
        $shape = shape([45]);

        expect($shape->busyOffsets)->toBe([[0, 45]])->and($shape->totalMinutes)->toBe(45);
    });

    it('leaves the processing time of a color free', function () {
        $shape = shape([20, 35, 25]);

        expect($shape->busyOffsets)->toBe([[0, 20], [55, 80]])->and($shape->totalMinutes)->toBe(80);
    });

    it('joins services booked back to back', function () {
        $shape = shape([30], [20]);

        expect($shape->busyOffsets)->toBe([[0, 50]])->and($shape->totalMinutes)->toBe(50);
    });

    it('chains a color and a cut keeping the gap', function () {
        $shape = shape([20, 35, 25], [30]);

        expect($shape->busyOffsets)->toBe([[0, 20], [55, 110]])->and($shape->totalMinutes)->toBe(110);
    });

    it('counts trailing idle time in the length of the visit but not as busy', function () {
        $shape = shape([15, 20, 0]);

        expect($shape->busyOffsets)->toBe([[0, 15]])->and($shape->totalMinutes)->toBe(35);
    });
});

describe('slots', function () {
    it('offers every step of the grid in which the service fits before closing', function () {
        $slots = slotsFor([span('09:00', '11:00')], [], shape([45]));

        expect($slots)->toBe(['09:00', '09:15', '09:30', '09:45', '10:00', '10:15']);
    });

    it('never offers a service that would run past closing time', function () {
        expect(slotsFor([span('09:00', '10:00')], [], shape([61])))->toBe([])
            ->and(slotsFor([span('09:00', '10:00')], [], shape([60])))->toBe(['09:00']);
    });

    it('keeps the grid independent of the duration', function () {
        $slots = slotsFor([span('09:00', '11:00')], [], shape([50]));

        expect($slots)->toContain('09:15')->toContain('10:00');
    });

    it('lets a booking start exactly when another ends, and end exactly when another starts', function () {
        $busy = [span('10:00', '10:30')];

        $slots = slotsFor([span('09:00', '12:00')], $busy, shape([30]));

        expect($slots)->toBe(['09:00', '09:15', '09:30', '10:30', '10:45', '11:00', '11:15', '11:30']);
    });

    it('refuses any overlap, even of one minute', function () {
        $calculator = new AvailabilityCalculator;
        $windows = [span('09:00', '12:00')];
        $busy = [span('10:00', '10:30')];

        expect($calculator->fits(at('09:31'), $windows, $busy, shape([30])))->toBeFalse()
            ->and($calculator->fits(at('10:29'), $windows, $busy, shape([30])))->toBeFalse()
            ->and($calculator->fits(at('09:30'), $windows, $busy, shape([30])))->toBeTrue()
            ->and($calculator->fits(at('10:30'), $windows, $busy, shape([30])))->toBeTrue();
    });

    it('sells the processing time of a color to someone else but protects its final stretch', function () {
        // Color 20 -> 35 idle -> 25, booked at 10:00: busy 10:00-10:20 and 10:55-11:20.
        $busy = shape([20, 35, 25])->busyAt(at('10:00'));

        $slots = slotsFor([span('10:00', '12:00')], $busy, shape([30]));

        // 10:20 is off the 15-minute grid: it is offered because the gap opens there.
        expect($slots)->toBe(['10:20', '11:20', '11:30'])->not->toContain('10:30');
    });

    it('offers the start of a gap that is off the grid', function () {
        $slots = slotsFor([span('09:00', '11:00')], [span('09:00', '09:50')], shape([40]));

        expect($slots)->toBe(['09:50', '10:00', '10:15']);
    });

    it('fits a short service exactly inside the processing gap', function () {
        $busy = shape([20, 35, 25])->busyAt(at('10:00'));

        expect(slotsFor([span('10:00', '12:00')], $busy, shape([35]), step: 5))->toContain('10:20')
            ->and(slotsFor([span('10:00', '12:00')], $busy, shape([36]), step: 5))->not->toContain('10:20');
    });

    it('places both stretches of a color around existing bookings', function () {
        $calculator = new AvailabilityCalculator;
        $windows = [span('09:00', '13:00')];
        $color = shape([20, 35, 25]);

        // Someone booked 09:30-09:50: inside the color's idle time when it starts at 09:05.
        expect($calculator->fits(at('09:05'), $windows, [span('09:30', '09:50')], $color))->toBeTrue()
            // ...but a booking at 10:00-10:30 collides with the final stretch (10:00-10:25).
            ->and($calculator->fits(at('09:05'), $windows, [span('10:00', '10:30')], $color))->toBeFalse();
    });

    it('lets idle time run over the lunch break but not a working stretch', function () {
        $windows = [span('09:00', '13:00'), span('15:00', '19:00')];
        $calculator = new AvailabilityCalculator;

        // 12:40 + 20 active = 13:00 (fits), idle until 15:00, finish 15:00-15:25.
        expect($calculator->fits(at('12:40'), $windows, [], shape([20, 120, 25])))->toBeTrue()
            // A plain 30 minute service at 12:45 would work past 13:00.
            ->and($calculator->fits(at('12:45'), $windows, [], shape([30])))->toBeFalse()
            // A stretch may not straddle two windows either.
            ->and($calculator->fits(at('12:30'), $windows, [], shape([180])))->toBeFalse();
    });

    it('respects time off like any other busy span', function () {
        $slots = slotsFor([span('09:00', '12:00')], [span('09:00', '11:00')], shape([30]));

        expect($slots)->toBe(['11:00', '11:15', '11:30']);
    });

    it('drops starts before the minimum notice and after the horizon', function () {
        $slots = slotsFor([span('09:00', '11:00')], [], shape([30]), earliest: at('09:40'), latest: at('10:15'));

        expect($slots)->toBe(['09:45', '10:00', '10:15']);
    });

    it('counts the grid from the opening of each window', function () {
        $slots = slotsFor([span('09:05', '10:00'), span('15:10', '16:00')], [], shape([30]), step: 20);

        expect($slots)->toBe(['09:05', '09:25', '15:10', '15:30']);
    });

    it('offers nothing without working hours or without anything to do', function () {
        expect(slotsFor([], [], shape([30])))->toBe([])
            ->and(slotsFor([span('09:00', '11:00')], [], BookingShape::fromServices([])))->toBe([]);
    });

    it('returns starts sorted and without duplicates', function () {
        $slots = slotsFor([span('15:00', '16:00'), span('09:00', '10:00')], [], shape([30]), step: 30);

        expect($slots)->toBe(['09:00', '09:30', '15:00', '15:30']);
    });
});

describe('working windows', function () {
    $ranges = [
        ['weekday' => 3, 'start' => '09:00', 'end' => '13:00', 'access' => ScheduleAccess::Public],
        ['weekday' => 3, 'start' => '13:00', 'end' => '15:00', 'access' => ScheduleAccess::Vip],
        ['weekday' => 3, 'start' => '18:00', 'end' => '20:00', 'access' => ScheduleAccess::StaffOnly],
        ['weekday' => 4, 'start' => '10:00', 'end' => '12:00', 'access' => ScheduleAccess::Public],
    ];
    $wednesday = CarbonImmutable::parse('2026-10-07 17:30', 'America/Lima');

    it('gives a regular client only the public hours of that weekday', function () use ($ranges, $wednesday) {
        $windows = WorkingWindows::forDate($wednesday, $ranges, [ScheduleAccess::Public]);

        expect($windows)->toHaveCount(1)
            ->and($windows[0]->start)->toBe(at('09:00'))
            ->and($windows[0]->end)->toBe(at('13:00'));
    });

    it('joins public and VIP hours into one window for a VIP client', function () use ($ranges, $wednesday) {
        $windows = WorkingWindows::forDate($wednesday, $ranges, [ScheduleAccess::Public, ScheduleAccess::Vip]);

        expect($windows)->toHaveCount(1)->and($windows[0]->end)->toBe(at('15:00'));
    });

    it('gives the team every range', function () use ($ranges, $wednesday) {
        expect(WorkingWindows::forDate($wednesday, $ranges, ScheduleAccess::cases()))->toHaveCount(2);
    });

    it('stores windows as absolute instants of the salon timezone', function () use ($ranges, $wednesday) {
        $windows = WorkingWindows::forDate($wednesday, $ranges, [ScheduleAccess::Public]);

        expect(CarbonImmutable::createFromTimestampUTC($windows[0]->start)->format('Y-m-d H:i'))->toBe('2026-10-07 14:00');
    });

    it('has no windows on a day without hours', function () use ($ranges) {
        $friday = CarbonImmutable::parse('2026-10-09', 'America/Lima');

        expect(WorkingWindows::forDate($friday, $ranges, ScheduleAccess::cases()))->toBe([]);
    });

    it('follows the wall clock across a daylight-saving change', function () {
        // Santiago moves its clocks forward on 2026-09-06: 09:00 is UTC-3 that day, UTC-4 the day before.
        $ranges = [
            ['weekday' => 6, 'start' => '09:00', 'end' => '10:00', 'access' => ScheduleAccess::Public],
            ['weekday' => 7, 'start' => '09:00', 'end' => '10:00', 'access' => ScheduleAccess::Public],
        ];
        $saturday = CarbonImmutable::parse('2026-09-05', 'America/Santiago');
        $sunday = CarbonImmutable::parse('2026-09-06', 'America/Santiago');

        $before = WorkingWindows::forDate($saturday, $ranges, [ScheduleAccess::Public])[0];
        $after = WorkingWindows::forDate($sunday, $ranges, [ScheduleAccess::Public])[0];

        expect(CarbonImmutable::createFromTimestampUTC($before->start)->format('H:i'))->toBe('13:00')
            ->and(CarbonImmutable::createFromTimestampUTC($after->start)->format('H:i'))->toBe('12:00');
    });
});
