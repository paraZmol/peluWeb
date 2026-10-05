<?php

namespace App\Enums;

/**
 * Extra powers an owner can grant to individual staff members.
 * Owners implicitly hold every permission.
 */
enum SalonPermission: string
{
    case ManageSalonProfile = 'manage_salon_profile';
    case ManageServices = 'manage_services';
    case ViewAllSchedules = 'view_all_schedules';
    case ManageCash = 'manage_cash';
    case ViewReports = 'view_reports';
    case ManageVipClients = 'manage_vip_clients';

    public function label(): string
    {
        return match ($this) {
            self::ManageSalonProfile => 'Editar el perfil del salón',
            self::ManageServices => 'Gestionar servicios y precios',
            self::ViewAllSchedules => 'Ver la agenda de todo el equipo',
            self::ManageCash => 'Cobrar y registrar gastos',
            self::ViewReports => 'Ver reportes de ganancias',
            self::ManageVipClients => 'Marcar clientes VIP',
        };
    }
}
