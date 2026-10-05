import { usePage } from '@inertiajs/react';
import type { SalonPermission } from '@/types';

/**
 * The salon the user is working on, with helpers for showing or hiding UI.
 * The server authorizes every action again; this only shapes the interface.
 */
export function useCurrentSalon() {
    const { currentSalon, salons } = usePage().props;

    const isOwner = currentSalon?.role === 'owner';

    const can = (permission: SalonPermission): boolean =>
        isOwner || (currentSalon?.permissions.includes(permission) ?? false);

    return { currentSalon, salons, isOwner, can };
}
