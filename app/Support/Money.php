<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is stored as integer céntimos. Forms send soles as text ("35.50");
 * the conversion is done on the string to avoid float rounding.
 */
final class Money
{
    public static function toCents(string|int|float $amount): int
    {
        $text = str_replace(',', '.', trim((string) $amount));

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $text)) {
            throw new InvalidArgumentException("Invalid amount [{$text}].");
        }

        [$units, $decimals] = array_pad(explode('.', $text), 2, '0');

        return (int) $units * 100 + (int) str_pad($decimals, 2, '0');
    }

    public static function toAmount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
