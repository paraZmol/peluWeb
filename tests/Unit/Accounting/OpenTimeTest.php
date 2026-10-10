<?php

use App\Domain\Accounting\OpenTime;
use App\Domain\Booking\TimeSpan;

const HOUR = 3600;

it('is the whole window when nothing blocks it', function () {
    expect(OpenTime::seconds([new TimeSpan(9 * HOUR, 13 * HOUR), new TimeSpan(15 * HOUR, 18 * HOUR)], []))->toBe(7 * HOUR);
});

it('takes off only the part of a block that falls inside the windows', function () {
    $windows = [new TimeSpan(9 * HOUR, 13 * HOUR), new TimeSpan(15 * HOUR, 18 * HOUR)];

    // 12–16 eats the last hour of the morning and the first of the afternoon.
    expect(OpenTime::seconds($windows, [new TimeSpan(12 * HOUR, 16 * HOUR)]))->toBe(5 * HOUR);
});

it('counts once the time two blocks share', function () {
    $blocked = [new TimeSpan(11 * HOUR, 13 * HOUR), new TimeSpan(10 * HOUR, 12 * HOUR)];

    expect(OpenTime::seconds([new TimeSpan(9 * HOUR, 18 * HOUR)], $blocked))->toBe(6 * HOUR);
});

it('is zero on a day fully off and ignores blocks that only touch', function () {
    $window = new TimeSpan(9 * HOUR, 18 * HOUR);

    expect(OpenTime::seconds([$window], [new TimeSpan(0, 24 * HOUR)]))->toBe(0)
        ->and(OpenTime::seconds([$window], [new TimeSpan(18 * HOUR, 20 * HOUR)]))->toBe(9 * HOUR);
});
