/** What a period added up to, in whole céntimos. */
export type ReportTotals = {
    /** Services plus kept deposits. */
    incomeCents: number;
    servicesCents: number;
    keptDepositsCents: number;
    expensesCents: number;
    /** Income minus expenses: negative when the period lost money. */
    profitCents: number;
    /** Already taken off what the services brought in. */
    discountsCents: number;
    visits: number;
};

/** One calendar day of the period ("2026-10-07"), also the empty ones. */
export type ReportDay = {
    date: string;
    incomeCents: number;
    expensesCents: number;
};

export type NamedAmount = { name: string; cents: number };

export type StaffSales = {
    name: string;
    visits: number;
    salesCents: number;
    commissionCents: number;
};

export type ServiceSales = { name: string; count: number; cents: number };

/** Money received by one method, deposits given back already taken off. */
export type MethodAmount = { value: string; label: string; cents: number };

export type LostVisits = {
    cancelled: number;
    cancelledCents: number;
    noShows: number;
    noShowCents: number;
};

/** Minutes one professional spent attending, against the minutes on duty. */
export type StaffOccupancy = {
    name: string;
    openMinutes: number;
    busyMinutes: number;
};

export type ClientMix = { new: number; returning: number };

export type Report = {
    totals: ReportTotals;
    /** The same figures for the period right before, of the same length. */
    previous: ReportTotals;
    days: ReportDay[];
    byCategory: NamedAmount[];
    byStaff: StaffSales[];
    byService: ServiceSales[];
    byMethod: MethodAmount[];
    lost: LostVisits;
    clients: ClientMix;
    occupancy: StaffOccupancy[];
};
