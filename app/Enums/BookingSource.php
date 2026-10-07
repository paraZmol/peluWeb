<?php

namespace App\Enums;

/**
 * Who made the booking: the client online, or the team for them (phone,
 * in person, walk-in).
 */
enum BookingSource: string
{
    case Client = 'client';
    case Staff = 'staff';
}
