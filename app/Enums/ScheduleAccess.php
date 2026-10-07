<?php

namespace App\Enums;

/**
 * Who may book inside a working-hours range. Enforced on the server: a
 * regular client is never offered VIP or staff-only ranges to book, and
 * VIP ranges are left out of the public opening hours.
 */
enum ScheduleAccess: string
{
    case Public = 'public';
    case Vip = 'vip';
    case StaffOnly = 'staff_only';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Todos',
            self::Vip => 'Solo clientes VIP',
            self::StaffOnly => 'Solo el equipo',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Public => 'Cualquier persona puede reservar',
            self::Vip => 'Reservado para tus clientes VIP',
            self::StaffOnly => 'Citas que anota el equipo (teléfono o en persona)',
        };
    }
}
