import { describe, expect, it } from 'vite-plus/test';
import type { MyDataCare, MyDataPhoto } from '@/components/my-data/types';
import {
    careStatus,
    careWithdrawnNotice,
    photosBlockedNotice,
    photosCountLabel,
    photosEraseWarning,
    photosInPersonNotice,
} from './my-data';

function care(overrides: Partial<MyDataCare> = {}): MyDataCare {
    return {
        isAuthorized: true,
        since: '2026-10-07',
        method: 'account',
        notes: null,
        withdrawnOn: null,
        withdrawnByThePerson: false,
        text: 'Autorizo a Salón Uno…',
        acceptLabel: 'Declaro que soy mayor de 18 años…',
        ...overrides,
    };
}

describe('careStatus', () => {
    it('says it was given from the account', () => {
        expect(careStatus(care(), 'Salón Uno')).toBe(
            'Autorizaste el 7 oct 2026',
        );
    });

    it('says a sheet was signed for any other method', () => {
        const onPaper =
            'Salón Uno registró que firmaste una hoja el 7 oct 2026. Si no fue así, retira la autorización.';

        expect(careStatus(care({ method: 'paper' }), 'Salón Uno')).toBe(
            onPaper,
        );
        expect(careStatus(care({ method: null }), 'Salón Uno')).toBe(onPaper);
    });

    it('is null while there is no authorization', () => {
        expect(
            careStatus(care({ isAuthorized: false, since: null }), 'Salón Uno'),
        ).toBeNull();
    });
});

describe('photosInPersonNotice', () => {
    const photo: MyDataPhoto = {
        id: 1,
        kindLabel: 'Antes',
        date: '2026-10-07',
        url: '/fotos/1',
    };

    it('says the salon recorded it when it was not given from the account', () => {
        const notice = 'Salón Uno registró que las autorizaste en persona.';

        expect(
            photosInPersonNotice(
                { items: [photo], method: 'paper' },
                'Salón Uno',
            ),
        ).toBe(notice);
    });

    it('is null for the account, or while there are no pictures', () => {
        expect(
            photosInPersonNotice(
                { items: [photo], method: 'account' },
                'Salón Uno',
            ),
        ).toBeNull();
        expect(
            photosInPersonNotice({ items: [], method: 'paper' }, 'Salón Uno'),
        ).toBeNull();
        // Pictures with no record of how they were allowed: nothing is claimed.
        expect(
            photosInPersonNotice({ items: [photo], method: null }, 'Salón Uno'),
        ).toBeNull();
    });
});

describe('careWithdrawnNotice', () => {
    it('says who took it back', () => {
        expect(
            careWithdrawnNotice(
                { withdrawnOn: '2026-10-07', withdrawnByThePerson: true },
                'Salón Uno',
            ),
        ).toBe('Retiraste esta autorización el 7 oct 2026.');
        expect(
            careWithdrawnNotice(
                { withdrawnOn: '2026-10-07', withdrawnByThePerson: false },
                'Salón Uno',
            ),
        ).toBe(
            'Salón Uno registró el retiro de esta autorización el 7 oct 2026.',
        );
    });
});

describe('photosCountLabel', () => {
    it('agrees in number', () => {
        expect(photosCountLabel(1)).toBe('1 foto');
        expect(photosCountLabel(4)).toBe('4 fotos');
    });
});

describe('photosEraseWarning', () => {
    it('says how many pictures are erased and where', () => {
        expect(photosEraseWarning(3, 'Salón Uno')).toBe(
            'Se borrarán de peluWeb las 3 fotos que Salón Uno guarda de tus servicios. No se pueden recuperar. Si el salón tiene copias en otro lugar, pídele que las borre.',
        );
    });

    it('says what stops when there is no picture yet', () => {
        expect(photosEraseWarning(0, 'Salón Uno')).toBe(
            'Salón Uno ya no podrá guardar fotos de tus servicios.',
        );
    });

    it('agrees in number with a single picture', () => {
        expect(photosEraseWarning(1, 'Salón Uno')).toBe(
            'Se borrará de peluWeb la foto que Salón Uno guarda de tus servicios. No se puede recuperar. Si el salón tiene copias en otro lugar, pídele que las borre.',
        );
    });
});

describe('photosBlockedNotice', () => {
    it('says the day they were taken back', () => {
        expect(photosBlockedNotice('2026-10-07', 'Salón Uno')).toBe(
            'Retiraste tu autorización el 7 oct 2026: Salón Uno no puede guardar fotos tuyas.',
        );
    });

    it('still reads well without the day', () => {
        expect(photosBlockedNotice(null, 'Salón Uno')).toBe(
            'Retiraste tu autorización: Salón Uno no puede guardar fotos tuyas.',
        );
    });
});
