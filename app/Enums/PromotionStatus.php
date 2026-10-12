<?php

namespace App\Enums;

/**
 * What the owner did with a promotion. Whether a published one is still
 * to come, running or over comes from its dates (see Promotion::state()).
 */
enum PromotionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
}
