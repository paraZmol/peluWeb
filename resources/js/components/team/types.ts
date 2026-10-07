import type { SalonPermission, SalonRole } from '@/types';

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    role: SalonRole;
    permissions: SalonPermission[];
    isYou: boolean;
};

/** A professional without an account, who an invitation can hand over. */
export type UnlinkedStaff = {
    id: number;
    name: string;
};

export type PendingInvitation = {
    id: number;
    email: string;
    role: SalonRole;
    expiresAt: string;
};
