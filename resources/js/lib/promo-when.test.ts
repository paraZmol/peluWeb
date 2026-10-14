import { describe, expect, it } from 'vite-plus/test';
import { shortWhen } from './promo-when';

const EVERY_DAY = 'lunes, martes, miércoles, jueves, viernes, sábado y domingo';

describe('shortWhen', () => {
    it('says nothing when it holds every day at any hour', () => {
        expect(shortWhen(null)).toBeNull();
        expect(shortWhen(EVERY_DAY)).toBeNull();
    });

    it('turns days in a row into a range, with 12 h hours', () => {
        expect(
            shortWhen(
                'lunes, martes, miércoles, jueves y viernes, de 15:00 a 19:00',
            ),
        ).toBe('lun a vie, 3:00 a 7:00 p. m.');
    });

    it('keeps both suffixes when the hours cross noon', () => {
        expect(shortWhen('lunes, martes y miércoles, de 10:00 a 16:00')).toBe(
            'lun a mié, 10:00 a. m. a 4:00 p. m.',
        );
    });

    it('lists loose days and only says the hours of every day', () => {
        expect(shortWhen('lunes, jueves, viernes, sábado y domingo')).toBe(
            'lun y jue a dom',
        );
        expect(shortWhen('martes y jueves')).toBe('mar y jue');
        expect(shortWhen('de 09:00 a 18:00')).toBe(
            'de 9:00 a. m. a 6:00 p. m.',
        );
        expect(shortWhen(`${EVERY_DAY}, de 00:00 a 23:30`)).toBe(
            'de 12:00 a. m. a 11:30 p. m.',
        );
    });

    it('shows what it cannot read as it came', () => {
        expect(shortWhen('feriados')).toBe('feriados');
    });
});
