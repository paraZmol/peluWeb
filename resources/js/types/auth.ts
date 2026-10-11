export type User = {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    /** Wants the day-before reminder of their bookings by email. */
    booking_reminders?: boolean;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    /** Runs the platform: their panel links to /plataforma. */
    is_platform_admin?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
