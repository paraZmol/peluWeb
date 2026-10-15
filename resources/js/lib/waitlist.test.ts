import { describe, expect, it } from 'vite-plus/test';
import { askedDay } from './waitlist';

const days = [
    { date: '2026-10-07', closed: false },
    { date: '2026-10-08', closed: false },
    { date: '2026-10-09', closed: true },
];

describe('askedDay', () => {
    it('gives the day of the link when it can be picked', () => {
        expect(askedDay('?fecha=2026-10-08', days)).toBe('2026-10-08');
    });

    it('lives with the other things a link may ask for', () => {
        expect(askedDay('?servicio=4&fecha=2026-10-08&continuar=1', days)).toBe(
            '2026-10-08',
        );
    });

    it('ignores a closed day, one out of range and anything else', () => {
        expect(askedDay('?fecha=2026-10-09', days)).toBeNull();
        expect(askedDay('?fecha=2027-01-01', days)).toBeNull();
        expect(askedDay('?fecha=mañana', days)).toBeNull();
        expect(askedDay('?servicio=4', days)).toBeNull();
        expect(askedDay('', days)).toBeNull();
    });
});
