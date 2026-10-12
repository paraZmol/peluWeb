<?php

namespace App\Enums;

/**
 * What the push service answered for one device.
 */
enum PushResult
{
    case Sent;

    /** The person removed the app or the permission: the subscription is dead. */
    case Gone;

    /** Did not go through this time; the device is kept. */
    case Failed;
}
