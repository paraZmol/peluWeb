<?php

use App\Domain\Booking\AvailabilityCalculator;
use App\Domain\Booking\BookingShape;
use App\Domain\Booking\TimeSpan;
use App\Domain\Booking\WorkingWindows;
use App\Enums\ScheduleAccess;
use Carbon\CarbonImmutable;

/*
| Attempts to break the pure availability code: partial overlaps, closing
| time, processing (idle) time, odd grids, daylight-saving days, and a
| seeded fuzz against an independent minute-by-minute oracle.
| Times are Lima wall clock on Wednesday 2026-10-07 unless stated.
*/

function brkAt(string $time, string $date = '2026-10-07', string $zone = 'America/Lima'): int
{
    return CarbonImmutable::parse("{$date} {$time}", $zone)->getTimestamp();
}

function brkSpan(string $from, string $to): TimeSpan
{
    return new TimeSpan(brkAt($from), brkAt($to));
}

/**
 * @param  array{0: int, 1?: int, 2?: int}  ...$services  [active, idle, finish]
 */
function brkShape(array ...$services): BookingShape
{
    return BookingShape::fromServices(array_map(fn (array $service): array => [
        'active' => $service[0],
        'idle' => $service[1] ?? 0,
        'finish' => $service[2] ?? 0,
    ], $services));
}

/**
 * @param  list<TimeSpan>  $windows
 * @param  list<TimeSpan>  $busy
 * @return list<string> "HH:MM" in Lima
 */
function brkSlots(array $windows, array $busy, BookingShape $shape, int $step = 15): array
{
    $slots = (new AvailabilityCalculator)->slots($windows, $busy, $shape, $step, 0, PHP_INT_MAX);

    return array_map(fn (int $start): string => CarbonImmutable::createFromTimestamp($start, 'America/Lima')->format('H:i'), $slots);
}

function brkFits(string $time, array $windows, array $busy, BookingShape $shape): bool
{
    return (new AvailabilityCalculator)->fits(brkAt($time), $windows, $busy, $shape);
}

/**
 * Independent oracle: paints every minute of the day as workable (and in
 * which window) or taken, and checks the visit minute by minute.
 *
 * @param  list<array{int, int}>  $windows  minutes from midnight, [from, to)
 * @param  list<array{int, int}>  $busy
 * @param  list<array{int, int, int}>  $services  [active, idle, finish]
 */
function brkOracleFits(int $startMinute, array $windows, array $busy, array $services): bool
{
    $cursor = $startMinute;
    $stretches = [];

    foreach ($services as [$active, $idle, $finish]) {
        $stretches[] = [$cursor, $cursor + $active];
        $cursor += $active + $idle;
        $stretches[] = [$cursor, $cursor + $finish];
        $cursor += $finish;
    }

    $busyMinutes = [];

    foreach ($stretches as [$from, $to]) {
        for ($minute = $from; $minute < $to; $minute++) {
            $busyMinutes[$minute] = true;
        }
    }

    if ($busyMinutes === []) {
        return false;
    }

    $minutes = array_keys($busyMinutes);
    sort($minutes);

    foreach ($minutes as $index => $minute) {
        foreach ($busy as [$from, $to]) {
            if ($minute >= $from && $minute < $to) {
                return false;
            }
        }

        $window = null;

        foreach ($windows as $key => [$from, $to]) {
            if ($minute >= $from && $minute < $to) {
                $window = $key;
            }
        }

        if ($window === null) {
            return false;
        }

        // Consecutive busy minutes must stay inside the same window: two
        // windows that touch were merged before reaching the calculator.
        $previous = $minutes[$index - 1] ?? null;

        if ($previous === $minute - 1 && ! ($previous >= $windows[$window][0])) {
            return false;
        }
    }

    return true;
}

describe('partial overlaps and touching ends', function () {
    it('rejects 10:30–11:00 against 10:00–10:45 and accepts 10:45–11:15', function () {
        $windows = [brkSpan('09:00', '18:00')];
        $busy = [brkSpan('10:00', '10:45')];
        $half = brkShape([30]);

        expect(brkFits('10:30', $windows, $busy, $half))->toBeFalse()
            ->and(brkFits('10:44', $windows, $busy, $half))->toBeFalse()
            ->and(brkFits('10:45', $windows, $busy, $half))->toBeTrue()
            ->and(brkFits('09:30', $windows, $busy, $half))->toBeTrue()
            ->and(brkFits('09:31', $windows, $busy, $half))->toBeFalse()
            // A visit that would swallow the busy span whole.
            ->and(brkFits('09:45', $windows, $busy, brkShape([90])))->toBeFalse()
            // … or sit entirely inside it.
            ->and(brkFits('10:10', $windows, $busy, brkShape([10])))->toBeFalse();
    });

    it('offers the instant a booking ends even when it is off the grid', function () {
        $slots = brkSlots([brkSpan('09:00', '12:00')], [brkSpan('10:00', '10:50')], brkShape([30]), 60);

        expect($slots)->toBe(['09:00', '10:50', '11:00']);
    });

    it('offers nothing a second before the previous booking ends', function () {
        $busy = [new TimeSpan(brkAt('10:00'), brkAt('10:45') + 1)];

        expect(brkFits('10:45', [brkSpan('09:00', '18:00')], $busy, brkShape([30])))->toBeFalse();
    });
});

describe('closing time, lunch and manual blocks', function () {
    it('never lets a visit run past closing, to the minute', function () {
        $windows = [brkSpan('09:00', '18:00')];

        expect(brkFits('17:15', $windows, [], brkShape([45])))->toBeTrue()
            ->and(brkFits('17:16', $windows, [], brkShape([45])))->toBeFalse()
            ->and(array_slice(brkSlots($windows, [], brkShape([45])), -2))->toBe(['17:00', '17:15'])
            // Longer than the whole day.
            ->and(brkSlots($windows, [], brkShape([541])))->toBe([])
            ->and(brkSlots($windows, [], brkShape([540])))->toBe(['09:00']);
    });

    it('does not let busy time cross the lunch break', function () {
        $windows = [brkSpan('09:00', '13:00'), brkSpan('14:00', '18:00')];
        $slots = brkSlots($windows, [], brkShape([60]));

        expect($slots)->toContain('12:00', '14:00')
            ->and(array_intersect($slots, ['12:15', '12:30', '12:45', '13:00', '13:15', '13:30', '13:45']))->toBe([]);
    });

    it('does not let busy time touch a manual block', function () {
        $slots = brkSlots([brkSpan('09:00', '18:00')], [brkSpan('15:00', '16:00')], brkShape([60]));

        expect($slots)->toContain('14:00', '16:00')
            ->and(array_intersect($slots, ['14:15', '14:30', '14:45', '15:00', '15:15', '15:30', '15:45']))->toBe([]);
    });

    it('lets the processing time, never the busy time, sit over the lunch break or a block', function () {
        $windows = [brkSpan('09:00', '13:00'), brkSpan('14:00', '18:00')];
        $color = brkShape([20, 60, 25]);

        // 12:40 apply · 13:00–14:00 processing over lunch · 14:00 finish.
        expect(brkFits('12:40', $windows, [], $color))->toBeTrue()
            // One minute later the application itself runs into lunch.
            ->and(brkFits('12:41', $windows, [], $color))->toBeFalse()
            // The finish would start during lunch.
            ->and(brkFits('12:30', $windows, [], $color))->toBeFalse();
    });
});

describe('processing (idle) time', function () {
    it('sells the gap of a color and protects its final stretch', function () {
        $windows = [brkSpan('09:00', '18:00')];
        $color = brkShape([20, 35, 25]);
        $taken = $color->busyAt(brkAt('10:00'));
        $slots = brkSlots($windows, $taken, brkShape([30]));

        expect($taken)->toHaveCount(2)
            ->and($slots)->toContain('09:30', '10:20', '11:20', '11:30')
            ->and(array_intersect($slots, ['09:45', '10:00', '10:15', '10:30', '10:45', '11:00', '11:15']))->toBe([])
            // Exactly the gap: 35 minutes fit, 36 do not.
            ->and(brkFits('10:20', $windows, $taken, brkShape([35])))->toBeTrue()
            ->and(brkFits('10:20', $windows, $taken, brkShape([36])))->toBeFalse();
    });

    it('fits a second color inside the first only where neither final stretch is stepped on', function () {
        $windows = [brkSpan('09:00', '18:00')];
        $color = brkShape([20, 35, 25]);
        $taken = $color->busyAt(brkAt('10:00'));

        // 10:20 apply, then its finish at 11:15 would hit … nothing: 10:55–11:20 is taken, so 11:15 collides.
        expect(brkFits('10:20', $windows, $taken, $color))->toBeFalse()
            // 10:25 → finish 11:20–11:45: free.
            ->and(brkFits('10:25', $windows, $taken, $color))->toBeTrue()
            // 10:36 → application 10:36–10:56 steps on the first finish.
            ->and(brkFits('10:36', $windows, $taken, $color))->toBeFalse();
    });

    it('does not offer a color whose final stretch falls after closing', function () {
        $windows = [brkSpan('09:00', '18:00')];
        $color = brkShape([20, 35, 25]);

        expect(brkFits('16:40', $windows, [], $color))->toBeTrue()
            ->and(brkFits('16:41', $windows, [], $color))->toBeFalse()
            ->and(array_slice(brkSlots($windows, [], $color), -1))->toBe(['16:30']);
    });

    it('chains several services with processing time into the right stretches', function () {
        $shape = brkShape([20, 35, 25], [30], [10, 15, 0], [20, 10, 5]);

        // 0–20 · idle · 55–80 + 80–110 + 110–120 joined · idle · 135–155 · idle · 165–170
        expect($shape->busyOffsets)->toBe([[0, 20], [55, 120], [135, 155], [165, 170]])
            ->and($shape->totalMinutes)->toBe(170);
    });

    it('has no start at all for a visit with nothing to do', function () {
        $windows = [brkSpan('09:00', '18:00')];

        expect(brkSlots($windows, [], brkShape([0])))->toBe([])
            ->and(brkSlots($windows, [], brkShape([0, 30, 0])))->toBe([])
            ->and(brkFits('10:00', $windows, [], brkShape([0, 30, 0])))->toBeFalse();
    });
});

describe('grids', function () {
    it('counts the grid from the opening of each window, for any step', function (int $step, array $expected) {
        expect(array_slice(brkSlots([brkSpan('09:10', '11:00')], [], brkShape([30]), $step), 0, 4))->toBe($expected);
    })->with([
        '5 minutes' => [5, ['09:10', '09:15', '09:20', '09:25']],
        '20 minutes' => [20, ['09:10', '09:30', '09:50', '10:10']],
        '60 minutes' => [60, ['09:10', '10:10']],
    ]);

    it('treats a zero or negative step as one minute instead of looping forever', function (int $step) {
        expect(brkSlots([brkSpan('09:00', '09:33')], [], brkShape([30]), $step))->toBe(['09:00', '09:01', '09:02', '09:03']);
    })->with([0, -15]);

    it('returns each start once, sorted, even when grid and booking ends coincide', function () {
        $busy = [brkSpan('09:30', '10:00'), brkSpan('09:00', '09:30'), brkSpan('11:00', '11:30')];
        $slots = brkSlots([brkSpan('09:00', '12:00')], $busy, brkShape([30]), 30);

        expect($slots)->toBe(['10:00', '10:30', '11:30']);
    });
});

describe('levels of access', function () {
    $ranges = [
        ['weekday' => 3, 'start' => '09:00', 'end' => '12:00', 'access' => ScheduleAccess::Public],
        ['weekday' => 3, 'start' => '12:00', 'end' => '15:00', 'access' => ScheduleAccess::Vip],
        ['weekday' => 3, 'start' => '15:00', 'end' => '18:00', 'access' => ScheduleAccess::StaffOnly],
        ['weekday' => 4, 'start' => '09:00', 'end' => '18:00', 'access' => ScheduleAccess::Vip],
    ];

    it('never gives a regular client a start whose visit touches VIP or team-only hours', function () use ($ranges) {
        $day = CarbonImmutable::parse('2026-10-07 12:00', 'America/Lima');
        $windows = WorkingWindows::forDate($day, $ranges, [ScheduleAccess::Public]);
        $slots = brkSlots($windows, [], brkShape([30]));

        expect($windows)->toHaveCount(1)
            ->and(end($slots))->toBe('11:30')
            ->and(brkFits('11:45', $windows, [], brkShape([30])))->toBeFalse()
            ->and(brkFits('12:00', $windows, [], brkShape([30])))->toBeFalse()
            ->and(WorkingWindows::forDate($day->addDay(), $ranges, [ScheduleAccess::Public]))->toBe([]);
    });

    it('gives a VIP the public and VIP hours as one window, and no team-only hour', function () use ($ranges) {
        $day = CarbonImmutable::parse('2026-10-07 12:00', 'America/Lima');
        $windows = WorkingWindows::forDate($day, $ranges, [ScheduleAccess::Public, ScheduleAccess::Vip]);
        $slots = brkSlots($windows, [], brkShape([30]));

        expect($windows)->toHaveCount(1)
            ->and($slots)->toContain('11:45', '12:00')
            ->and(end($slots))->toBe('14:30')
            ->and(brkFits('14:45', $windows, [], brkShape([30])))->toBeFalse();
    });
});

describe('time zones and daylight saving', function () {
    $everyDay = fn (string $start, string $end): array => array_map(
        fn (int $weekday): array => ['weekday' => $weekday, 'start' => $start, 'end' => $end, 'access' => ScheduleAccess::Public],
        range(1, 7),
    );

    it('places the windows of a day on the salon clock wherever the salon is', function (string $zone, string $opensUtc) use ($everyDay) {
        $day = CarbonImmutable::parse('2026-10-07 12:00', $zone);
        $windows = WorkingWindows::forDate($day, $everyDay('09:00', '18:00'), [ScheduleAccess::Public]);

        expect($windows)->toHaveCount(1)
            ->and(CarbonImmutable::createFromTimestampUTC($windows[0]->start)->format('Y-m-d H:i'))->toBe($opensUtc)
            ->and($windows[0]->end - $windows[0]->start)->toBe(9 * 3600);
    })->with([
        'Lima (UTC-5)' => ['America/Lima', '2026-10-07 14:00'],
        'Tokyo (UTC+9)' => ['Asia/Tokyo', '2026-10-07 00:00'],
        'Kiritimati (UTC+14): the day before in UTC' => ['Pacific/Kiritimati', '2026-10-06 19:00'],
        'Niue (UTC-11)' => ['Pacific/Niue', '2026-10-07 20:00'],
    ]);

    it('gives the same instants whatever time zone the server process runs in', function () use ($everyDay) {
        $compute = fn (): array => (new AvailabilityCalculator)->slots(
            WorkingWindows::forDate(CarbonImmutable::parse('2026-10-07 23:30', 'America/Lima'), $everyDay('19:00', '23:55'), [ScheduleAccess::Public]),
            [],
            brkShape([30]),
            15,
            0,
            PHP_INT_MAX,
        );

        $original = date_default_timezone_get();
        $inUtc = $compute();
        date_default_timezone_set('Asia/Tokyo');

        try {
            $inTokyo = $compute();
        } finally {
            date_default_timezone_set($original);
        }

        // 19:00 in Lima is already the next day in UTC.
        expect($inTokyo)->toBe($inUtc)
            ->and(CarbonImmutable::createFromTimestampUTC($inUtc[0])->format('Y-m-d H:i'))->toBe('2026-10-08 00:00')
            ->and(CarbonImmutable::createFromTimestamp(end($inUtc), 'America/Lima')->format('Y-m-d H:i'))->toBe('2026-10-07 23:15');
    });

    it('keeps a day that loses an hour (Santiago, clocks jump at midnight) coherent', function () use ($everyDay) {
        // Sunday 2026-09-06 starts at 01:00: the hour 00:00–01:00 does not exist.
        $day = CarbonImmutable::parse('2026-09-06 12:00', 'America/Santiago');
        expect($day->startOfDay()->diffInHours($day->addDay()->startOfDay()))->toBe(23.0);

        $windows = WorkingWindows::forDate($day, $everyDay('00:00', '06:00'), [ScheduleAccess::Public]);
        $slots = (new AvailabilityCalculator)->slots($windows, [], brkShape([60]), 60, 0, PHP_INT_MAX);
        $times = array_map(fn (int $start): string => CarbonImmutable::createFromTimestamp($start, 'America/Santiago')->format('H:i'), $slots);

        // Five real hours of work, not six, and no start in the missing hour.
        expect($windows[0]->end - $windows[0]->start)->toBe(5 * 3600)
            ->and($times)->toBe(['01:00', '02:00', '03:00', '04:00', '05:00']);
    });

    it('keeps a day that repeats an hour (Santiago, 25 hours) coherent', function () use ($everyDay) {
        // Saturday 2027-04-03: at 24:00 clocks go back to 23:00.
        $day = CarbonImmutable::parse('2027-04-03 12:00', 'America/Santiago');
        expect($day->startOfDay()->diffInHours($day->addDay()->startOfDay()))->toBe(25.0);

        $windows = WorkingWindows::forDate($day, $everyDay('20:00', '23:55'), [ScheduleAccess::Public]);
        $slots = (new AvailabilityCalculator)->slots($windows, [], brkShape([30]), 15, 0, PHP_INT_MAX);
        $times = array_map(fn (int $start): string => CarbonImmutable::createFromTimestamp($start, 'America/Santiago')->format('H:i'), $slots);

        // The working range is read once (first 23:00–23:55), so no clock time is offered twice.
        expect($windows[0]->end - $windows[0]->start)->toBe(235 * 60)
            ->and($times)->toBe(array_values(array_unique($times)))
            ->and(end($times))->toBe('23:15');
    });
});

describe('fuzz against a minute-by-minute oracle', function () {
    it('agrees with the oracle on every minute of 150 generated days', function () {
        mt_srand(20261007);
        $calculator = new AvailabilityCalculator;
        $midnight = brkAt('00:00');
        $toSpan = fn (array $range): TimeSpan => new TimeSpan($midnight + $range[0] * 60, $midnight + $range[1] * 60);
        $disagreements = [];

        foreach (range(1, 150) as $case) {
            // Windows: up to three, never touching (the caller merges those).
            $windows = [];
            $cursor = mt_rand(0, 10) * 30;

            foreach (range(1, mt_rand(1, 3)) as $ignored) {
                $from = $cursor + mt_rand(0, 6) * 5;
                $to = $from + mt_rand(3, 60) * 5;

                if ($to > 1435) {
                    break;
                }

                $windows[] = [$from, $to];
                $cursor = $to + mt_rand(1, 12) * 5;
            }

            if ($windows === []) {
                continue;
            }

            // Busy spans anywhere, any length, overlapping each other or not.
            $busy = [];

            foreach (range(1, mt_rand(0, 6)) as $ignored) {
                $from = mt_rand(0, 1380);
                $busy[] = [$from, $from + mt_rand(1, 120)];
            }

            $services = [];

            foreach (range(1, mt_rand(1, 3)) as $ignored) {
                $hasIdle = mt_rand(0, 2) === 0;
                $services[] = [mt_rand(1, 18) * 5, $hasIdle ? mt_rand(1, 12) * 5 : 0, $hasIdle ? mt_rand(0, 6) * 5 : 0];
            }

            $step = [5, 15, 20, 60][mt_rand(0, 3)];
            $shape = brkShape(...$services);
            $windowSpans = array_map($toSpan, $windows);
            $busySpans = array_map($toSpan, $busy);
            $expected = [];

            for ($minute = 0; $minute < 1440; $minute++) {
                $oracle = brkOracleFits($minute, $windows, $busy, $services);

                if ($oracle !== $calculator->fits($midnight + $minute * 60, $windowSpans, $busySpans, $shape)) {
                    $disagreements[] = "case {$case}: fits() at minute {$minute} should be ".var_export($oracle, true);
                }

                $onGrid = array_filter($windows, fn (array $window): bool => $minute >= $window[0] && $minute < $window[1] && ($minute - $window[0]) % $step === 0) !== [];
                $afterBooking = in_array($minute, array_column($busy, 1), true);

                if ($oracle && ($onGrid || $afterBooking)) {
                    $expected[] = $midnight + $minute * 60;
                }
            }

            if ($calculator->slots($windowSpans, $busySpans, $shape, $step, 0, PHP_INT_MAX) !== $expected) {
                $disagreements[] = "case {$case}: slots() differ from the oracle";
            }

            // Notice and horizon only ever cut the list at its ends.
            $earliest = $midnight + mt_rand(0, 1439) * 60;
            $latest = $earliest + mt_rand(0, 600) * 60;
            $bounded = array_values(array_filter($expected, fn (int $start): bool => $start >= $earliest && $start <= $latest));

            if ($calculator->slots($windowSpans, $busySpans, $shape, $step, $earliest, $latest) !== $bounded) {
                $disagreements[] = "case {$case}: slots() ignore the earliest/latest bounds";
            }
        }

        expect(array_slice($disagreements, 0, 10))->toBe([]);
    });
});
