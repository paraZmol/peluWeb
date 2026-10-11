import type {
    ClientFile,
    ClientNotesFields,
    ClientPhoto,
} from '@/components/clients/types';
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

/**
 * Since when they are a client: the first visit when the record was created
 * after it (a history loaded later), the record's day otherwise.
 */
export function clientSince(
    since: string | null,
    firstVisit: string | null,
): string | null {
    if (since === null || firstVisit === null) {
        return since ?? firstVisit;
    }

    // "Y-m-d" sorts as text.
    return firstVisit < since ? firstVisit : since;
}

/** One thing to keep in mind before attending, and where it is written. */
export type BriefLine = {
    key: 'care' | 'formula' | 'notes';
    label: string;
    text: string;
    /** The field of the notes form it jumps to. */
    href: string;
    /** An allergy on the file: it must stand out. */
    isAlert: boolean;
};

const NO_CARE_NOTES = 'Sin alergias anotadas';

/**
 * What to read first with the person in the chair: allergies (only for who
 * may see them), formula and preferences. Nothing written, no lines.
 */
export function attendingBrief(
    client: ClientFile,
    canSeeCare: boolean,
): BriefLine[] {
    const care = canSeeCare ? (client.careNotes ?? '').trim() : '';
    const formula = (client.formulaNotes ?? '').trim();
    const notes = (client.notes ?? '').trim();

    if (care === '' && formula === '' && notes === '') {
        return [];
    }

    const lines: BriefLine[] = [
        {
            key: 'care',
            label: 'Alergias',
            text: care === '' ? NO_CARE_NOTES : care,
            href: '#client-care-notes-field',
            isAlert: care !== '',
        },
        {
            key: 'formula',
            label: 'Fórmula',
            text: formula,
            href: '#client-formula-notes-field',
            isAlert: false,
        },
        {
            key: 'notes',
            label: 'Preferencias',
            text: notes,
            href: '#client-notes-field',
            isAlert: false,
        },
    ];

    return lines.filter((line) =>
        line.key === 'care' ? canSeeCare : line.text !== '',
    );
}

/** "Antes · 7 oct 2026": what a picture is, for who cannot see it. */
export function photoCaption(photo: ClientPhoto): string {
    return `${photo.kindLabel} · ${formatLongDay(photo.date)}`;
}

/** The pictures of each day together, in the order they arrive. */
export function groupPhotosByDay(
    photos: ClientPhoto[],
): { date: string; photos: ClientPhoto[] }[] {
    const days = new Map<string, ClientPhoto[]>();

    for (const photo of photos) {
        days.set(photo.date, [...(days.get(photo.date) ?? []), photo]);
    }

    return [...days].map(([date, ofDay]) => ({ date, photos: ofDay }));
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
