<?php

namespace App\Enums;

/**
 * The two kinds of sheet in a Libro de Reclamaciones. "Queja" is about how
 * someone was treated; "Reclamo", about the service itself.
 */
enum ComplaintKind: string
{
    case Claim = 'claim';
    case Grievance = 'grievance';

    public function label(): string
    {
        return match ($this) {
            self::Claim => 'Reclamo',
            self::Grievance => 'Queja',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::Claim => 'No estás conforme con el servicio.',
            self::Grievance => 'No estás conforme con la atención, aunque el servicio haya funcionado.',
        };
    }
}
