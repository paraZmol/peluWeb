<?php

use App\Domain\Accounting\Checkout;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentException;

/*
| Pure arithmetic of settling a visit, in céntimos.
*/

it('charges the price minus the deposit already paid when nothing else is said', function () {
    $checkout = Checkout::inFull(PaymentMethod::Cash, priceCents: 3500, depositPaidCents: 1050);

    $checkout->assertSettles(3500, 1050);

    expect($checkout->chargedCents(3500))->toBe(3500)
        ->and($checkout->dueCents(3500, 1050))->toBe(2450)
        ->and($checkout->receivedParts())->toBe(['cash' => 2450]);
});

it('adds the tip to what is collected but not to what the visit cost', function () {
    $checkout = new Checkout(discountCents: 500, tipCents: 300, parts: ['cash' => 2000, 'yape' => 1300]);

    $checkout->assertSettles(3500, 0);

    expect($checkout->chargedCents(3500))->toBe(3000)
        ->and($checkout->dueCents(3500, 0))->toBe(3300);
});

it('records no payment when the deposit already covered a discounted visit', function () {
    $checkout = new Checkout(discountCents: 2450, parts: ['cash' => 0]);

    $checkout->assertSettles(3500, 1050);

    expect($checkout->receivedParts())->toBe([]);
});

it('refuses numbers that do not hold together', function (Checkout $checkout, int $price, int $deposit) {
    expect(fn () => $checkout->assertSettles($price, $deposit))->toThrow(PaymentException::class);
})->with([
    'a céntimo short' => [new Checkout(parts: ['cash' => 3499]), 3500, 0],
    'a céntimo over' => [new Checkout(parts: ['cash' => 3501]), 3500, 0],
    'ignoring the deposit already paid' => [new Checkout(parts: ['cash' => 3500]), 3500, 1050],
    'tip left out of the parts' => [new Checkout(tipCents: 500, parts: ['cash' => 3500]), 3500, 0],
    'discount above the price' => [new Checkout(discountCents: 3501, parts: []), 3500, 0],
    'discount eating a paid deposit' => [new Checkout(discountCents: 3000, parts: []), 3500, 1050],
    'negative discount' => [new Checkout(discountCents: -100, parts: ['cash' => 3600]), 3500, 0],
    'negative tip' => [new Checkout(tipCents: -100, parts: ['cash' => 3400]), 3500, 0],
    'negative part offset by another' => [new Checkout(parts: ['cash' => 4000, 'yape' => -500]), 3500, 0],
    'four methods' => [new Checkout(parts: ['cash' => 1000, 'yape' => 1000, 'plin' => 1000, 'card' => 500]), 3500, 0],
]);
