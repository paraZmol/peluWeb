<?php

use App\Domain\Complaints\BusinessDays;
use Carbon\CarbonImmutable;

/*
| 2026-10-16 is a Friday. 2026-10-08 (Combate de Angamos) and 2026-11-01
| (Todos los Santos, a Sunday) are holidays.
*/

function peruCalendar(): BusinessDays
{
    return new BusinessDays(['10-08', '11-01', '12-08', '12-25', '2026-04-03']);
}

function peruDate(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date);
}

it('counts fifteen business days from the day after filing, skipping weekends', function () {
    // Friday: Monday 19 is day 1; three full weeks end on Friday 6 November.
    expect(peruCalendar()->addTo(peruDate('2026-10-16'), 15)->format('Y-m-d'))->toBe('2026-11-06');
});

it('starts counting on Monday when filed on a weekend', function () {
    expect(peruCalendar()->addTo(peruDate('2026-10-17'), 1)->format('Y-m-d'))->toBe('2026-10-19')
        ->and(peruCalendar()->addTo(peruDate('2026-10-18'), 15)->format('Y-m-d'))->toBe('2026-11-06');
});

it('skips holidays that repeat every year and one-off dates', function () {
    // Wed 7 Oct: Thu 8 is a holiday, so day 1 is Fri 9.
    expect(peruCalendar()->addTo(peruDate('2026-10-07'), 1)->format('Y-m-d'))->toBe('2026-10-09')
        ->and(peruCalendar()->addTo(peruDate('2027-10-07'), 1)->format('Y-m-d'))->toBe('2027-10-11')
        // Holy Thursday 2026-04-02 is not in this list; Good Friday is.
        ->and(peruCalendar()->addTo(peruDate('2026-04-02'), 1)->format('Y-m-d'))->toBe('2026-04-06');
});

it('skips both December holidays and Christmas', function () {
    // Mon 7 Dec: Tue 8 holiday → 9, 10, 11, 14 … 15 business days land on Wed 30 Dec.
    expect(peruCalendar()->addTo(peruDate('2026-12-07'), 15)->format('Y-m-d'))->toBe('2026-12-30');
});

it('tells the business days left, zero on the last day and negative after it', function () {
    $due = peruDate('2026-11-06');

    expect(peruCalendar()->leftUntil(peruDate('2026-10-30'), $due))->toBe(5)
        ->and(peruCalendar()->leftUntil(peruDate('2026-11-04'), $due))->toBe(2)
        ->and(peruCalendar()->leftUntil(peruDate('2026-11-06'), $due))->toBe(0)
        ->and(peruCalendar()->leftUntil(peruDate('2026-11-09'), $due))->toBe(-1);
});

it('does not count a weekend as time left', function () {
    // Friday to Monday is one business day.
    expect(peruCalendar()->leftUntil(peruDate('2026-10-16'), peruDate('2026-10-19')))->toBe(1);
});
