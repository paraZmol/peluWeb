<?php

namespace App\Enums;

/**
 * Where an account accepted a legal text.
 */
enum AcceptanceChannel: string
{
    /** The sign-up form with email and password (casilla 1). */
    case Registration = 'registration';

    /** The screen shown before a Google sign-up creates the account (casilla 2). */
    case Google = 'google';

    /** The screen of an account without the current version (casilla 3). */
    case Screen = 'screen';

    /** Opening a salon (casilla 5). */
    case SalonSignup = 'salon_signup';

    /** Demo and test data; never a real person. */
    case Seeded = 'seeded';
}
