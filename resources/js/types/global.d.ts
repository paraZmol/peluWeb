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
            features: { googleLogin: boolean };
            currentSalon: CurrentSalon | null;
            salons: SalonSummary[];
            [key: string]: unknown;
        };
    }
}
