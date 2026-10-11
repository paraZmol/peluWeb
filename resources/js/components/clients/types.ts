import type { BookingStatus } from '@/components/booking/types';

/** Who recorded the authorization, the day (salon's calendar) and how it was given. */
export type CareConsent = {
    date: string;
    by: string | null;
    /** "account": the person gave it from their account; otherwise a signed sheet. */
    method: string | null;
};

/** An authorization taken back, by the person themselves or on their behalf. */
export type ConsentWithdrawal = { date: string; byThePerson: boolean };

/** The pictures taken back, and how many were erased with it. */
export type PhotosWithdrawal = ConsentWithdrawal & { count: number };

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
    /** The latest word on the care notes is a withdrawal; null for who may not read them. */
    careWithdrawal: ConsentWithdrawal | null;
    photosWithdrawal: PhotosWithdrawal | null;
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
