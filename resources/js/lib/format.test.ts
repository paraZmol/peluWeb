import { describe, expect, it } from 'vite-plus/test';
import { formatLongDate, formatTime } from './format';

describe('formatLongDate', () => {
    it('writes the weekday, the day and the month in full', () => {
        expect(formatLongDate('2026-10-08')).toBe('jueves 8 de octubre');
    });

    it('does not shift the day at the edges of a month', () => {
        expect(formatLongDate('2026-11-01')).toBe('domingo 1 de noviembre');
        expect(formatLongDate('2026-12-31')).toBe('jueves 31 de diciembre');
    });
});

describe('formatTime', () => {
    it('reads the 24 h text from the server as a 12 h time', () => {
        expect(formatTime('09:05')).toBe('9:05 a. m.');
        expect(formatTime('15:30')).toBe('3:30 p. m.');
        expect(formatTime('00:00')).toBe('12:00 a. m.');
        expect(formatTime('12:00')).toBe('12:00 p. m.');
    });
});
