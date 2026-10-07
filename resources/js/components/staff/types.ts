export type StaffSummary = {
    id: number;
    name: string;
    title: string | null;
    photoUrl: string | null;
    hasOwnPhoto: boolean;
    isActive: boolean;
    userId: number | null;
    accountEmail: string | null;
    /** ISO weekdays with at least one working range. */
    workDays: number[];
    servicesCount: number;
};

export type LinkableMember = {
    userId: number;
    name: string;
    email: string;
};

export type Holiday = {
    id: number;
    date: string;
    name: string;
};
