<?php

namespace App\Support;

/**
 * Names copied from an account into salon records (professionals, clients).
 * Account names are longer and may carry characters salon texts never
 * allow, since those texts end up in public pages and WhatsApp messages.
 */
final class PersonName
{
    public static function clean(string $name, int $maxLength, string $fallback): string
    {
        $plain = trim((string) preg_replace('/[<>\[\]{}]/u', '', $name));

        return mb_substr($plain, 0, $maxLength) ?: $fallback;
    }
}
