import type { BookingStatus } from '@/components/booking/types';

/** Who recorded the signed authorization, and the day (salon's calendar). */
export type CareConsent = { date: string; by: string | null };

export type ClientFile = {
    id: number;
    name: string;
    phone: string | null;
    whatsappUrl: string | null;
    isVip: boolean;
    hasAccount: boolean;
    /** "2026-10-07" on the salon's calendar. */
    since: string | null;
    notes: string | null;
    formulaNotes: string | null;
    /** Health data: null for whoever may not read it. */
    careNotes: string | null;
    careConsent: CareConsent | null;
};

export type ClientStats = {
    visits: number;
    cancelled: number;
    noShows: number;
    firstVisit: string | null;
    lastVisit: string | null;
    /** Soles as text ("35.00"); null for whoever does not handle money. */
    spent: string | null;
};

export type ClientVisit = {
    id: number;
    date: string;
    /** "11:00", 24 h, on the salon's clock. */
    time: string;
    status: BookingStatus;
    statusLabel: string;
    staffName: string;
    services: string[];
    note: string | null;
    charged: string | null;
};

/** What the team writes on the file, as the server receives it. */
export type ClientNotesFields = {
    notes: string;
    formula_notes: string;
    care_notes: string;
    care_consent: boolean;
};

export type ClientPhotoKind = 'before' | 'after';

/** A picture of the work, kept only for the salon's own record. */
export type ClientPhoto = {
    id: number;
    kind: ClientPhotoKind;
    /** "Antes" or "Después". */
    kindLabel: string;
    /** The day it was taken, "2026-10-07" on the salon's calendar. */
    date: string;
    /** Private: served only to who may see the file's care notes. */
    url: string;
};
