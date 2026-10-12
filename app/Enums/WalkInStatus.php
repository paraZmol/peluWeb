<?php

namespace App\Enums;

/**
 * Where someone who came without a booking stands in the day's line.
 */
enum WalkInStatus: string
{
    case Waiting = 'waiting';
    /** Their turn: someone is free for them. */
    case Called = 'called';
    case Attended = 'attended';
    /** Got tired of waiting, or did not answer when called. */
    case Left = 'left';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'En espera',
            self::Called => 'Llamado',
            self::Attended => 'Atendido',
            self::Left => 'Se fue',
        };
    }

    /** Still in the shop, waiting or being called. */
    public function isOpen(): bool
    {
        return $this === self::Waiting || $this === self::Called;
    }

    /**
     * What the team may do next: call, attend or note that they left; a
     * called person may go back to waiting. Closed places stay closed.
     */
    public function canBecome(self $next): bool
    {
        return match ($this) {
            self::Waiting => in_array($next, [self::Called, self::Attended, self::Left], true),
            self::Called => in_array($next, [self::Waiting, self::Attended, self::Left], true),
            self::Attended, self::Left => false,
        };
    }
}
