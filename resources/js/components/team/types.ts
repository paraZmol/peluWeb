import type { SalonPermission, SalonRole } from '@/types';

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    role: SalonRole;
    permissions: SalonPermission[];
    isYou: boolean;
};

export type PendingInvitation = {
    id: number;
    email: string;
    role: SalonRole;
    expiresAt: string;
};
