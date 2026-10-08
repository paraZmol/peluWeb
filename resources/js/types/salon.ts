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
    brand: BrandTokens | null;
    /** The user attends clients in this salon (has a professional profile). */
    hasStaffProfile: boolean;
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

/** Design template of a salon's public pages (App\Enums\SalonTheme). */
export type SalonTheme = 'editorial' | 'nocturna' | 'luminosa' | 'vibrante';

export type BrandTokens = {
    light: { primary: string; foreground: string };
    dark: { primary: string; foreground: string };
    adjusted: boolean;
};
