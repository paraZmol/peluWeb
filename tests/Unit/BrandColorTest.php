<?php

use App\Support\BrandColor;

it('keeps a well balanced color as is in both themes', function () {
    $tokens = BrandColor::tokens('#8F5E45');

    expect($tokens['light']['primary'])->toBe('#8f5e45')
        ->and($tokens['dark']['primary'])->toBe('#8f5e45')
        ->and($tokens['light']['foreground'])->toBe('#ffffff')
        ->and($tokens['adjusted'])->toBeFalse();
});

it('always makes button text readable (≥ 4.5:1)', function (string $color) {
    $tokens = BrandColor::tokens($color);

    foreach (['light', 'dark'] as $mode) {
        expect(BrandColor::contrast($tokens[$mode]['primary'], $tokens[$mode]['foreground']))
            ->toBeGreaterThanOrEqual(4.5);
    }
})->with(['#8f5e45', '#ffeb3b', '#000000', '#ffffff', '#e91e63', '#00bcd4', '#777777', '#123']);

it('makes the brand stand out from the page background (≥ 3:1)', function (string $color) {
    $tokens = BrandColor::tokens($color);

    expect(BrandColor::contrast($tokens['light']['primary'], BrandColor::LIGHT_BACKGROUND))->toBeGreaterThanOrEqual(3.0)
        ->and(BrandColor::contrast($tokens['dark']['primary'], BrandColor::DARK_BACKGROUND))->toBeGreaterThanOrEqual(3.0);
})->with(['#ffeb3b', '#f5f5f5', '#000000', '#1a1a2e', '#e91e63']);

it('darkens a pastel yellow for light mode and reports the adjustment', function () {
    $tokens = BrandColor::tokens('#fff59d');

    expect($tokens['light']['primary'])->not->toBe('#fff59d')
        ->and($tokens['adjusted'])->toBeTrue();
});

it('accepts short hex and rejects garbage', function () {
    expect(BrandColor::normalize('#AbC'))->toBe('#aabbcc');
    expect(fn () => BrandColor::normalize('rojo'))->toThrow(InvalidArgumentException::class);
    expect(fn () => BrandColor::normalize('#12345g'))->toThrow(InvalidArgumentException::class);
});

it('turns a dark neutral into a light button with dark text for dark mode', function (string $color) {
    $dark = BrandColor::tokens($color)['dark'];

    expect($dark['foreground'])->toBe('#1f1b18')
        ->and(BrandColor::contrast($dark['primary'], BrandColor::DARK_BACKGROUND))->toBeGreaterThanOrEqual(10.0);
})->with(['#1f1b18', '#1f2937', '#000000']);

it('keeps the hue of a dark saturated color in dark mode', function () {
    expect(BrandColor::tokens('#1d4ed8')['dark'])->toBe(['primary' => '#2857da', 'foreground' => '#ffffff']);
});
