<?php

namespace App\Support;

/**
 * Builds wa.me links on the server (never by hand in the UI).
 */
final class WhatsApp
{
    /** Peru: local mobile numbers have 9 digits and start with 9. */
    private const string DEFAULT_COUNTRY_CODE = '51';

    public static function link(?string $phone, string $message = ''): ?string
    {
        $digits = self::digits($phone);

        if ($digits === null) {
            return null;
        }

        $query = $message === '' ? '' : '?text='.rawurlencode($message);

        return "https://wa.me/{$digits}{$query}";
    }

    /**
     * International digits without "+", adding Peru's code to local numbers.
     */
    public static function digits(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === null || strlen($digits) < 7) {
            return null;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return self::DEFAULT_COUNTRY_CODE.$digits;
        }

        return $digits;
    }
}
