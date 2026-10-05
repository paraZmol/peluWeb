export type SalonRole = 'owner' | 'staff';

export type SalonPermission =
    | 'manage_salon_profile'
    | 'manage_services'
    | 'view_all_schedules'
    | 'manage_cash'
    | 'view_reports'
    | 'manage_vip_clients';

export type CurrentSalon = {
    id: number;
    name: string;
    slug: string;
    role: SalonRole;
    permissions: SalonPermission[];
};

export type SalonSummary = {
    id: number;
    name: string;
    role: SalonRole;
};

export type Option<T extends string = string> = {
    value: T;
    label: string;
};
