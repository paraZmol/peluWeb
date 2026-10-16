import type { Auth } from '@/types/auth';
import type { CurrentSalon, SalonSummary } from '@/types/salon';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            features: {
                googleLogin: boolean;
                /** Public key to subscribe a device to notifications; null when off. */
                pushKey: string | null;
            };
            currentSalon: CurrentSalon | null;
            salons: SalonSummary[];
            /** The platform admin is looking at the current salon for support (read only). */
            support: { salonName: string; endsAt: string } | null;
            /** Who provides the service, for the legal footer; empty values are null. */
            legal: {
                providerName: string | null;
                ruc: string | null;
                contactEmail: string | null;
                complaintsUrl: string | null;
            };
            [key: string]: unknown;
        };
    }
}
