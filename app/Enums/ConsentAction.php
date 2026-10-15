<?php

namespace App\Enums;

enum ConsentAction: string
{
    case Granted = 'granted';
    case Withdrawn = 'withdrawn';
}
