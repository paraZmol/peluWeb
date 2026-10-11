<?php

namespace App\Enums;

/**
 * What a client authorized a salon to keep about them.
 */
enum ConsentKind: string
{
    /** Allergies and reactions: health data. */
    case Care = 'care';

    /** Before/after pictures for the salon's own record, never published. */
    case Photos = 'photos';

    /**
     * How the salon gets it: health data needs the person's signature;
     * for pictures, whoever attends them declares they agreed.
     */
    public function method(): ConsentMethod
    {
        return match ($this) {
            self::Care => ConsentMethod::Paper,
            self::Photos => ConsentMethod::StaffDeclared,
        };
    }

    /** The current wording the person signs; bump it when the text changes. */
    public function textVersion(): string
    {
        return match ($this) {
            self::Care => '0.3',
            self::Photos => '0.1',
        };
    }
}
