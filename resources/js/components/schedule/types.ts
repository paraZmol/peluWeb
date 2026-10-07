import type { Option } from '@/types';

export type AccessLevel = 'public' | 'vip' | 'staff_only';

export type AccessOption = Option<AccessLevel> & { hint: string };

export type ScheduleRange = {
    weekday: number;
    /** "HH:MM", 24 h, salon local time. */
    start: string;
    end: string;
    accessLevel: AccessLevel;
};

export type TimeOffItem = {
    id: number;
    startsOn: string;
    endsOn: string;
    allDay: boolean;
    startTime: string;
    endTime: string;
    reason: string;
    note: string | null;
};

export type StaffServiceItem = {
    id: number;
    name: string;
    isActive: boolean;
    baseMinutes: number;
    waitMinutes: number;
    basePrice: string;
    assigned: boolean;
    activeMinutes: number | null;
    price: string | null;
};

export type StaffEditor = {
    id: number;
    name: string;
    title: string | null;
    photoUrl: string | null;
    hasOwnPhoto: boolean;
    isActive: boolean;
    ranges: ScheduleRange[];
    timeOff: TimeOffItem[];
    services: StaffServiceItem[];
};
