import type { ClientFile, ClientNotesFields } from '@/components/clients/types';
import { formatDate } from './format';

const longDayFormat = new Intl.DateTimeFormat('es-PE', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

/**
 * "2026-10-07" → "7 oct 2026": a file spans years, so the year always shows.
 * Built from parts so no timezone shifts it.
 */
export function formatLongDay(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return longDayFormat
        .format(new Date(year, month - 1, day))
        .replace(/[.,]/g, '')
        .replace(/ de /g, ' ')
        .toLowerCase();
}

/** "2026-10-07" → "mié 7 oct 2026": the day of a visit, weekday included. */
export function formatVisitDay(date: string): string {
    return `${formatDate(date)} ${date.slice(0, 4)}`;
}

/** 1 → "1 vez", 3 → "3 veces". */
export function timesLabel(count: number): string {
    return count === 1 ? '1 vez' : `${count} veces`;
}

/** What the form starts from: the saved notes, and the consent on record. */
export function initialNotesFields(client: ClientFile): ClientNotesFields {
    return {
        notes: client.notes ?? '',
        formula_notes: client.formulaNotes ?? '',
        care_notes: client.careNotes ?? '',
        care_consent: client.careConsent !== null,
    };
}

/**
 * What is sent. Without the authorization the care notes go empty (the
 * server erases them anyway); whoever may not read them sends nothing about
 * them, so theirs stay as they are.
 */
export function notesPayload(
    fields: ClientNotesFields,
    canSeeCare: boolean,
): Partial<ClientNotesFields> {
    const { notes, formula_notes } = fields;

    if (!canSeeCare) {
        return { notes, formula_notes };
    }

    return {
        notes,
        formula_notes,
        care_notes: fields.care_consent ? fields.care_notes : '',
        care_consent: fields.care_consent,
    };
}

/**
 * Saving without the authorization erases care notes already on the file,
 * and that cannot be undone: it is asked first.
 */
export function erasesSavedCare(
    client: ClientFile,
    fields: ClientNotesFields,
    canSeeCare: boolean,
): boolean {
    const hasSavedCare = (client.careNotes ?? '').trim() !== '';

    return canSeeCare && hasSavedCare && !fields.care_consent;
}
