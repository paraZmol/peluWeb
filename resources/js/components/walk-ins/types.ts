export type WalkInStatus = 'waiting' | 'called' | 'attended' | 'left';

/** Someone who came without a booking, as the day's line shows them. */
export type WalkIn = {
    id: number;
    name: string;
    serviceName: string | null;
    /** Who they asked for; null: whoever is free first. */
    staffName: string | null;
    status: WalkInStatus;
    statusLabel: string;
    /** "10:05", on the salon's clock. */
    arrivedAt: string | null;
    calledAt: string | null;
};

export type WalkInOption = { id: number; name: string };
