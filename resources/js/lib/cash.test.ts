import { describe, expect, it } from 'vite-plus/test';
import {
    describeDifference,
    formatBalance,
    signedCents,
    splitMethods,
} from './cash';

describe('signedCents', () => {
    it('reads the sign without passing through a float', () => {
        expect(signedCents('92.70')).toBe(9270);
        expect(signedCents('-3.00')).toBe(-300);
        expect(signedCents('0.00')).toBe(0);
    });
});

describe('formatBalance', () => {
    it('puts the sign before the currency', () => {
        expect(formatBalance(4270)).toBe('S/ 42.70');
        expect(formatBalance(-1230)).toBe('− S/ 12.30');
    });
});

describe('describeDifference', () => {
    it('names what is missing, what is left over and an exact count', () => {
        expect(describeDifference(0)).toEqual({
            tone: 'even',
            label: 'Cuadra',
        });
        expect(describeDifference(-200)).toEqual({
            tone: 'short',
            label: 'Faltan S/ 2.00',
        });
        expect(describeDifference(150)).toEqual({
            tone: 'over',
            label: 'Sobran S/ 1.50',
        });
    });

    it('tells a closed session in the past', () => {
        expect(describeDifference(0, true).label).toBe('Cuadró');
        expect(describeDifference(-200, true).label).toBe('Faltaron S/ 2.00');
        expect(describeDifference(150, true).label).toBe('Sobraron S/ 1.50');
    });
});

describe('splitMethods', () => {
    it('keeps cash apart from the other methods', () => {
        const row = (value: string) => ({
            value,
            label: value,
            in: '0.00',
            out: '0.00',
            netCents: 0,
        });
        const { cash, others } = splitMethods([row('cash'), row('yape')]);

        expect(cash?.value).toBe('cash');
        expect(others.map((method) => method.value)).toEqual(['yape']);
    });
});
