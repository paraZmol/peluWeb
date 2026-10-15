import { describe, expect, it } from 'vite-plus/test';
import type {
    ClientFile,
    ClientNotesFields,
    ClientPhoto,
} from '@/components/clients/types';
import {
    attendingBrief,
    careWithdrawalNotice,
    clientSince,
    erasesSavedCare,
    formatLongDay,
    formatVisitDay,
    groupPhotosByDay,
    initialNotesFields,
    isCareAuthorizedFromAccount,
    isCareLockedByThePerson,
    notesPayload,
    photoCaption,
    photosWithdrawalNotice,
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
        careWithdrawal: null,
        photosWithdrawal: null,
        ...overrides,
    };
}

function fields(overrides: Partial<ClientNotesFields> = {}): ClientNotesFields {
    return {
        notes: 'Poco conversar',
        formula_notes: '7.1 + 20 vol',
        care_notes: 'Alergia a la PPD',
        care_consent: true,
        care_minor: false,
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

function photo(overrides: Partial<ClientPhoto>): ClientPhoto {
    return {
        id: 1,
        kind: 'before',
        kindLabel: 'Antes',
        date: '2026-10-07',
        url: '/clientes/1/fotos/1',
        ...overrides,
    };
}

describe('attendingBrief', () => {
    const full = client({
        notes: 'Café sin azúcar',
        formulaNotes: '7.1 + 20 vol',
        careNotes: 'Alergia al látex',
    });

    it('puts the allergies first and marks them', () => {
        const lines = attendingBrief(full, true);

        expect(lines.map((line) => line.key)).toEqual([
            'care',
            'formula',
            'notes',
        ]);
        expect(lines[0]).toMatchObject({
            text: 'Alergia al látex',
            isAlert: true,
            href: '#client-care-notes-field',
        });
    });

    it('never says "no allergies": none written means ask', () => {
        const lines = attendingBrief(client({ notes: 'Café' }), true);

        expect(lines).toHaveLength(2);
        expect(lines[0]).toMatchObject({
            text: 'Sin alergias registradas. Pregunta antes de aplicar productos.',
            isAlert: false,
            isNotice: false,
        });
    });

    it('warns of a withdrawal even with nothing else written', () => {
        const withdrawn = client({
            careWithdrawal: { date: '2026-10-07', byThePerson: true },
        });
        const lines = attendingBrief(withdrawn, true);

        expect(lines).toHaveLength(1);
        expect(lines[0]).toMatchObject({
            key: 'care',
            text: careWithdrawalNotice(withdrawn),
            isAlert: true,
            isNotice: true,
        });
        expect(attendingBrief(withdrawn, false)).toEqual([]);
    });

    it('says nothing about allergies to who may not read them', () => {
        const lines = attendingBrief(full, false);

        expect(lines.map((line) => line.key)).toEqual(['formula', 'notes']);
    });

    it('has no lines when nothing is written', () => {
        expect(attendingBrief(client(), true)).toEqual([]);
        expect(attendingBrief(client({ notes: '  ' }), false)).toEqual([]);
    });
});

describe('isCareAuthorizedFromAccount', () => {
    it('is true only for an authorization given from the account', () => {
        const consent = { date: '2026-10-07', by: null };

        expect(
            isCareAuthorizedFromAccount(
                client({ careConsent: { ...consent, method: 'account' } }),
            ),
        ).toBe(true);
        expect(
            isCareAuthorizedFromAccount(
                client({ careConsent: { ...consent, method: 'paper' } }),
            ),
        ).toBe(false);
        expect(isCareAuthorizedFromAccount(client())).toBe(false);
    });
});

describe('careWithdrawalNotice', () => {
    const REST =
        'para guardar alergias. Lo anotado se borró. No lo anotes en otro campo. Pregúntale por alergias antes de cada servicio.';

    it('names the person when they took it back themselves', () => {
        const withdrawn = client({
            careWithdrawal: { date: '2026-10-07', byThePerson: true },
        });

        expect(careWithdrawalNotice(withdrawn)).toBe(
            `Carla Cliente retiró el 7 oct 2026 su autorización ${REST}`,
        );
    });

    it('is impersonal when it was taken back at the salon', () => {
        const withdrawn = client({
            careWithdrawal: { date: '2026-10-07', byThePerson: false },
        });

        expect(careWithdrawalNotice(withdrawn)).toBe(
            `Se retiró el 7 oct 2026 la autorización ${REST}`,
        );
    });

    it('is null without a withdrawal or once authorized again', () => {
        expect(careWithdrawalNotice(client())).toBeNull();
        expect(
            careWithdrawalNotice(
                client({
                    careWithdrawal: { date: '2026-10-07', byThePerson: true },
                    careConsent: {
                        date: '2026-10-08',
                        by: 'Ana',
                        method: 'paper',
                    },
                }),
            ),
        ).toBeNull();
    });
});

describe('isCareLockedByThePerson', () => {
    const withdrawal = { date: '2026-10-07', byThePerson: true };

    it('is true while their own withdrawal stands', () => {
        expect(
            isCareLockedByThePerson(client({ careWithdrawal: withdrawal })),
        ).toBe(true);
    });

    it('is false when the salon withdrew it, or once authorized again', () => {
        expect(isCareLockedByThePerson(client())).toBe(false);
        expect(
            isCareLockedByThePerson(
                client({
                    careWithdrawal: { ...withdrawal, byThePerson: false },
                }),
            ),
        ).toBe(false);
        expect(
            isCareLockedByThePerson(
                client({
                    careWithdrawal: withdrawal,
                    careConsent: {
                        date: '2026-10-08',
                        by: null,
                        method: 'account',
                    },
                }),
            ),
        ).toBe(false);
    });
});

describe('photosWithdrawalNotice', () => {
    const withdrawal = { date: '2026-10-07', byThePerson: true, count: 3 };

    it('says how many were erased and who can allow them again', () => {
        expect(
            photosWithdrawalNotice(client({ photosWithdrawal: withdrawal })),
        ).toBe(
            'Carla Cliente retiró el 7 oct 2026 su autorización para fotos. Se borraron las 3 fotos de su ficha. Si hay copias en un teléfono u otro lugar, bórralas también. Solo Carla Cliente puede volver a autorizarlas, desde su cuenta.',
        );
    });

    it('agrees in number, and skips the count when there were none', () => {
        expect(
            photosWithdrawalNotice(
                client({ photosWithdrawal: { ...withdrawal, count: 1 } }),
            ),
        ).toContain('fotos. Se borró la foto de su ficha. Si hay');
        expect(
            photosWithdrawalNotice(
                client({ photosWithdrawal: { ...withdrawal, count: 0 } }),
            ),
        ).toContain('para fotos. Si hay copias');
    });

    it('is null unless the person took them back themselves', () => {
        expect(photosWithdrawalNotice(client())).toBeNull();
        expect(
            photosWithdrawalNotice(
                client({
                    photosWithdrawal: { ...withdrawal, byThePerson: false },
                }),
            ),
        ).toBeNull();
    });
});

describe('clientSince', () => {
    it('takes the earlier of the record and the first visit', () => {
        expect(clientSince('2026-10-10', '2026-10-09')).toBe('2026-10-09');
        expect(clientSince('2026-01-05', '2026-10-09')).toBe('2026-01-05');
    });

    it('falls back to whichever is known', () => {
        expect(clientSince('2026-10-10', null)).toBe('2026-10-10');
        expect(clientSince(null, '2026-10-09')).toBe('2026-10-09');
        expect(clientSince(null, null)).toBeNull();
    });
});

describe('photoCaption', () => {
    it('says when in the service and the day', () => {
        expect(photoCaption(photo({}))).toBe('Antes · 7 oct 2026');
    });
});

describe('groupPhotosByDay', () => {
    it('keeps the order and puts each day together', () => {
        const groups = groupPhotosByDay([
            photo({ id: 3, date: '2026-10-09', kind: 'after' }),
            photo({ id: 2, date: '2026-10-09' }),
            photo({ id: 1, date: '2026-10-07' }),
        ]);

        expect(groups.map((group) => group.date)).toEqual([
            '2026-10-09',
            '2026-10-07',
        ]);
        expect(groups[0].photos.map((item) => item.id)).toEqual([3, 2]);
        expect(groupPhotosByDay([])).toEqual([]);
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
            care_minor: false,
        });
    });

    it('starts with the consent checked when one is on record', () => {
        const saved = client({
            notes: 'Café sin azúcar',
            careNotes: 'Alergia al látex',
            careConsent: { date: '2026-10-07', by: 'Ana', method: 'paper' },
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
            care_minor: false,
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
        careConsent: { date: '2026-10-07', by: 'Ana', method: 'paper' },
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
