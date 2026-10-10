import { describe, expect, it } from 'vite-plus/test';
import type { ClientFile, ClientNotesFields } from '@/components/clients/types';
import {
    erasesSavedCare,
    formatLongDay,
    formatVisitDay,
    initialNotesFields,
    notesPayload,
    timesLabel,
} from './clients';

function client(overrides: Partial<ClientFile> = {}): ClientFile {
    return {
        id: 1,
        name: 'Carla Cliente',
        phone: null,
        whatsappUrl: null,
        isVip: false,
        hasAccount: false,
        since: '2026-10-07',
        notes: null,
        formulaNotes: null,
        careNotes: null,
        careConsent: null,
        ...overrides,
    };
}

function fields(overrides: Partial<ClientNotesFields> = {}): ClientNotesFields {
    return {
        notes: 'Poco conversar',
        formula_notes: '7.1 + 20 vol',
        care_notes: 'Alergia a la PPD',
        care_consent: true,
        ...overrides,
    };
}

describe('formatLongDay', () => {
    it('shows day, short month and year without dots or "de"', () => {
        expect(formatLongDay('2026-10-07')).toBe('7 oct 2026');
        expect(formatLongDay('2025-01-31')).toBe('31 ene 2025');
    });
});

describe('formatVisitDay', () => {
    it('adds the weekday and keeps the year', () => {
        expect(formatVisitDay('2026-10-07')).toBe('mié 7 oct 2026');
    });
});

describe('timesLabel', () => {
    it('agrees in number', () => {
        expect(timesLabel(1)).toBe('1 vez');
        expect(timesLabel(4)).toBe('4 veces');
    });
});

describe('initialNotesFields', () => {
    it('starts empty and without consent on a new file', () => {
        expect(initialNotesFields(client())).toEqual({
            notes: '',
            formula_notes: '',
            care_notes: '',
            care_consent: false,
        });
    });

    it('starts with the consent checked when one is on record', () => {
        const saved = client({
            notes: 'Café sin azúcar',
            careNotes: 'Alergia al látex',
            careConsent: { date: '2026-10-07', by: 'Ana' },
        });

        expect(initialNotesFields(saved)).toMatchObject({
            notes: 'Café sin azúcar',
            care_notes: 'Alergia al látex',
            care_consent: true,
        });
    });
});

describe('notesPayload', () => {
    it('sends the care notes only with the consent', () => {
        expect(notesPayload(fields(), true)).toEqual(fields());
        expect(notesPayload(fields({ care_consent: false }), true)).toEqual({
            notes: 'Poco conversar',
            formula_notes: '7.1 + 20 vol',
            care_notes: '',
            care_consent: false,
        });
    });

    it('sends nothing about care for who may not read it', () => {
        expect(notesPayload(fields(), false)).toEqual({
            notes: 'Poco conversar',
            formula_notes: '7.1 + 20 vol',
        });
    });
});

describe('erasesSavedCare', () => {
    const saved = client({
        careNotes: 'Alergia al látex',
        careConsent: { date: '2026-10-07', by: 'Ana' },
    });

    it('is true when the consent is withdrawn with notes on the file', () => {
        expect(
            erasesSavedCare(saved, fields({ care_consent: false }), true),
        ).toBe(true);
    });

    it('is false while the consent stays, or nothing was saved yet', () => {
        expect(erasesSavedCare(saved, fields(), true)).toBe(false);
        expect(
            erasesSavedCare(client(), fields({ care_consent: false }), true),
        ).toBe(false);
        expect(
            erasesSavedCare(
                client({ careNotes: '  ' }),
                fields({ care_consent: false }),
                true,
            ),
        ).toBe(false);
    });

    it('is false for who may not read the care notes', () => {
        expect(
            erasesSavedCare(saved, fields({ care_consent: false }), false),
        ).toBe(false);
    });
});
