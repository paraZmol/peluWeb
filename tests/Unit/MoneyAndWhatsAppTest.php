<?php

use App\Support\Money;
use App\Support\WhatsApp;

it('converts soles typed by people into céntimos without float errors', function (string $input, int $cents) {
    expect(Money::toCents($input))->toBe($cents);
})->with([
    ['35', 3500],
    ['35.5', 3550],
    ['35.50', 3550],
    ['35,50', 3550],
    ['0.10', 10],
    ['19.99', 1999],
    ['1250', 125000],
]);

it('rejects amounts that are not money', function (string $input) {
    expect(fn () => Money::toCents($input))->toThrow(InvalidArgumentException::class);
})->with(['abc', '-5', '35.555', '', '3e2']);

it('formats céntimos as an amount', function () {
    expect(Money::toAmount(3550))->toBe('35.50')
        ->and(Money::toAmount(0))->toBe('0.00');
});

it('builds wa.me links adding the Peru code to local mobiles', function () {
    expect(WhatsApp::link('999888777', 'Hola'))->toBe('https://wa.me/51999888777?text=Hola')
        ->and(WhatsApp::link('+51 999 888 777'))->toBe('https://wa.me/51999888777')
        ->and(WhatsApp::link('+54 9 11 2345 6789'))->toBe('https://wa.me/5491123456789')
        ->and(WhatsApp::link(null))->toBeNull()
        ->and(WhatsApp::link('123'))->toBeNull();
});
