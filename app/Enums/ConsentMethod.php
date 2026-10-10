<?php

namespace App\Enums;

/**
 * How an authorization reached the salon.
 */
enum ConsentMethod: string
{
    /** Signed on paper by the person; the salon keeps the sheet. */
    case Paper = 'paper';
}
