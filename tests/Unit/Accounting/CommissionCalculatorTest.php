<?php

use App\Domain\Accounting\CommissionCalculator;

/*
| Pure arithmetic of a professional's commission for one visit, in céntimos.
*/

function line(int $priceCents, int $percent = 0, ?int $fixedCents = null): array
{
    return ['priceCents' => $priceCents, 'percent' => $percent, 'fixedCents' => $fixedCents];
}

it('pays each service its own rule', function () {
    // 40 % of 35.00 + a fixed 5.00 for the beard trim + nothing for the wash.
    expect(CommissionCalculator::forVisit([line(3500, 40), line(2000, 40, 500), line(1000)], 0))->toBe(1400 + 500);
});

it('rounds half up to the céntimo', function () {
    expect(CommissionCalculator::forVisit([line(3335, 50)], 0))->toBe(1668)
        ->and(CommissionCalculator::forVisit([line(3333, 50)], 0))->toBe(1667)
        ->and(CommissionCalculator::forVisit([line(1, 49)], 0))->toBe(0);
});

it('spreads the discount in proportion before applying the rules', function () {
    // 10.00 off 60.00: 5.83 off the 35.00 line, the remaining 4.17 off the 25.00 one.
    // 50 % of 29.17 = 14.59 (14.585 rounded up); 50 % of 20.83 = 10.42 (10.415 rounded up).
    expect(CommissionCalculator::forVisit([line(3500, 50), line(2500, 50)], 1000))->toBe(1459 + 1042);
});

it('never pays more than the service ended up costing', function () {
    expect(CommissionCalculator::forVisit([line(1000, 0, 5000)], 0))->toBe(1000)
        ->and(CommissionCalculator::forVisit([line(1000, 0, 800)], 500))->toBe(500)
        ->and(CommissionCalculator::forVisit([line(1000, 100)], 1000))->toBe(0)
        ->and(CommissionCalculator::forVisit([line(1000, 50)], 9999))->toBe(0);
});

it('handles a visit with no services or free services', function () {
    expect(CommissionCalculator::forVisit([], 0))->toBe(0)
        ->and(CommissionCalculator::forVisit([line(0, 50), line(0, 0, 300)], 0))->toBe(0);
});

it('uses the whole discount exactly once across the lines', function (int $discount) {
    $lines = [line(3333, 100), line(3333, 100), line(3334, 100)];

    // At 100 % the commission is what was charged: price minus discount.
    expect(CommissionCalculator::forVisit($lines, $discount))->toBe(10000 - $discount);
})->with([0, 1, 2, 3, 100, 999, 5000, 9999, 10000]);
