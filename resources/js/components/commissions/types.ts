/** A service the professional performs, with its own rule if it has one. */
export type ServiceRule = {
    id: number;
    name: string;
    /** 0-100; null when the service has no percent of its own. */
    percent: number | null;
    /** A fixed amount in soles ("5.00"); both null = the general percent. */
    amount: string | null;
};

export type StaffCommission = {
    id: number;
    name: string;
    isActive: boolean;
    /** The general rule: 0-100 of every service. */
    percent: number;
    /** Completed visits of the period whose commission is not paid yet. */
    visits: number;
    /** What those visits sold, soles as text ("55.00"). */
    sales: string;
    pending: string;
    pendingCents: number;
    services: ServiceRule[];
};

export type Settlement = {
    id: number;
    staffName: string;
    /** The period that was paid, both days included ("2026-10-01"). */
    from: string;
    to: string;
    /** Soles as text ("19.00"). */
    total: string;
    visits: number;
    /** The day it was paid on the salon's calendar. */
    paidOn: string;
};

/** The days on screen, both included ("2026-10-01"). */
export type Period = { from: string; to: string };
