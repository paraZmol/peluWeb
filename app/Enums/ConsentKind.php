<?php

namespace App\Enums;

/**
 * What a client authorized a salon to keep about them.
 */
enum ConsentKind: string
{
    /** Allergies and reactions: health data. */
    case Care = 'care';

    /** The current wording the person signs; bump it when the text changes. */
    public function textVersion(): string
    {
        return match ($this) {
            self::Care => '0.2',
        };
    }
}
