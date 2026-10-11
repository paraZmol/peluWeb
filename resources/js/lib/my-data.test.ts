import { describe, expect, it } from 'vite-plus/test';
import type { MyDataCare } from '@/components/my-data/types';
import {
    careStatus,
    photosBlockedNotice,
    photosCountLabel,
    photosEraseWarning,
} from './my-data';

function care(overrides: Partial<MyDataCare> = {}): MyDataCare {
    return {
        isAuthorized: true,
        since: '2026-10-07',
        method: 'account',
        notes: null,
        withdrawnOn: null,
        text: 'Autorizo a Salón Uno…',
        ...overrides,
    };
}

describe('careStatus', () => {
    it('says it was given from the account', () => {
        expect(careStatus(care())).toBe('Autorizaste el 7 oct 2026');
    });

    it('says a sheet was signed for any other method', () => {
        expect(careStatus(care({ method: 'paper' }))).toBe(
            'Firmaste una hoja en el salón el 7 oct 2026',
        );
        expect(careStatus(care({ method: null }))).toBe(
            'Firmaste una hoja en el salón el 7 oct 2026',
        );
    });

    it('is null while there is no authorization', () => {
        expect(
            careStatus(care({ isAuthorized: false, since: null })),
        ).toBeNull();
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
            'Se borrarán las 3 fotos que Salón Uno guarda de tus servicios. No se pueden recuperar.',
        );
    });

    it('says what stops when there is no picture yet', () => {
        expect(photosEraseWarning(0, 'Salón Uno')).toBe(
            'Salón Uno ya no podrá guardar fotos de tus servicios.',
        );
    });

    it('agrees in number with a single picture', () => {
        expect(photosEraseWarning(1, 'Salón Uno')).toBe(
            'Se borrará la foto que Salón Uno guarda de tus servicios. No se puede recuperar.',
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
