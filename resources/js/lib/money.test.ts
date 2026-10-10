import { describe, expect, it } from 'vite-plus/test';
import { resolveCheckout } from './checkout';
import { fromCents, toCents } from './money';

describe('toCents', () => {
    it('reads soles as whole céntimos', () => {
        expect(toCents('35')).toBe(3500);
        expect(toCents('24.5')).toBe(2450);
        expect(toCents('24,50')).toBe(2450);
        expect(toCents('0.07')).toBe(7);
        expect(toCents(' 10.00 ')).toBe(1000);
    });

    it('does not drift where floats do', () => {
        expect(toCents('0.1')! + toCents('0.2')!).toBe(30);
        expect(toCents('19.99')).toBe(1999);
        expect(toCents('1.15')).toBe(115);
    });

    it('takes an empty field as nothing', () => {
        expect(toCents('')).toBe(0);
    });

    it('rejects what is not an amount', () => {
        for (const text of ['abc', '-5', '1.234', '1.', '.5', '1e3', '1 0']) {
            expect(toCents(text), text).toBeNull();
        }
    });
});

describe('fromCents', () => {
    it('writes céntimos the way the server does', () => {
        expect(fromCents(2450)).toBe('24.50');
        expect(fromCents(7)).toBe('0.07');
        expect(fromCents(0)).toBe('0.00');
        expect(fromCents(-150)).toBe('-1.50');
    });
});

describe('resolveCheckout', () => {
    const base = {
        balanceCents: 2450,
        discount: '',
        lastIsAuto: true,
    };
    const cash = { method: 'cash', amount: '' };

    it('charges the whole balance to a single method', () => {
        const state = resolveCheckout({ ...base, parts: [cash] });

        expect(state.totalCents).toBe(2450);
        expect(state.partCents).toEqual([2450]);
        expect(state.problem).toBeNull();
    });

    it('takes the discount off', () => {
        const state = resolveCheckout({
            ...base,
            discount: '4.50',
            parts: [cash],
        });

        expect(state.discountCents).toBe(450);
        expect(state.totalCents).toBe(2000);
        expect(state.partCents).toEqual([2000]);
    });

    it('refuses a discount that is not an amount', () => {
        expect(
            resolveCheckout({ ...base, discount: 'cinco', parts: [cash] })
                .problem,
        ).toBe('Revisa los montos: usa números como 20 o 20.50.');
    });

    it('gives the rest to the last method of a split', () => {
        const state = resolveCheckout({
            ...base,
            parts: [
                { method: 'cash', amount: '10' },
                { method: 'yape', amount: '' },
            ],
        });

        expect(state.partCents).toEqual([1000, 1450]);
        expect(state.problem).toBeNull();
    });

    it('says how much is missing or left over', () => {
        const parts = [
            { method: 'cash', amount: '10' },
            { method: 'yape', amount: '10' },
        ];

        expect(
            resolveCheckout({ ...base, lastIsAuto: false, parts }).problem,
        ).toBe('Faltan S/ 4.50 para llegar al total.');
        expect(
            resolveCheckout({
                ...base,
                balanceCents: 1500,
                lastIsAuto: false,
                parts,
            }).problem,
        ).toBe('Sobran S/ 5.00: los montos pasan del total.');
    });

    it('refuses a discount above the balance and a method left empty', () => {
        expect(
            resolveCheckout({ ...base, discount: '24.51', parts: [cash] })
                .problem,
        ).toBe('El descuento no puede pasar de S/ 24.50.');
        expect(
            resolveCheckout({
                ...base,
                parts: [cash, { method: 'yape', amount: '' }],
            }).problem,
        ).toMatch(/^Escribe cuánto/);
    });

    it('needs no payment when the deposit covered it all', () => {
        const state = resolveCheckout({
            ...base,
            balanceCents: 0,
            parts: [cash],
        });

        expect(state.totalCents).toBe(0);
        expect(state.problem).toBeNull();
    });
});
