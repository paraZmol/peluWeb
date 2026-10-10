import { describe, expect, it } from 'vite-plus/test';
import type { StaffCommission } from '@/components/commissions/types';
import {
    describeRules,
    formatPeriod,
    pendingTotals,
    periodShortcuts,
    ruleMode,
    shiftDay,
    yearBefore,
} from './commissions';

const member = (overrides: Partial<StaffCommission>): StaffCommission => ({
    id: 1,
    name: 'Pedro',
    isActive: true,
    percent: 40,
    visits: 0,
    sales: '0.00',
    pending: '0.00',
    pendingCents: 0,
    services: [],
    ...overrides,
});

describe('periodShortcuts', () => {
    it('counts this month, last month and the week from Monday', () => {
        // 2026-10-10 is a Saturday.
        expect(periodShortcuts('2026-10-10')).toEqual([
            { label: 'Este mes', from: '2026-10-01', to: '2026-10-10' },
            { label: 'Mes pasado', from: '2026-09-01', to: '2026-09-30' },
            { label: 'Esta semana', from: '2026-10-05', to: '2026-10-10' },
        ]);
    });

    it('crosses the year and starts the week on a Monday or a Sunday', () => {
        // 2026-01-04 is a Sunday, 2026-01-05 a Monday.
        const [, lastMonth, week] = periodShortcuts('2026-01-04');

        expect(lastMonth).toMatchObject({
            from: '2025-12-01',
            to: '2025-12-31',
        });
        expect(week.from).toBe('2025-12-29');
        expect(periodShortcuts('2026-01-05')[2].from).toBe('2026-01-05');
    });
});

describe('shiftDay and yearBefore', () => {
    it('move on the calendar without a timezone', () => {
        expect(shiftDay('2026-03-01', -1)).toBe('2026-02-28');
        expect(shiftDay('2026-12-31', 1)).toBe('2027-01-01');
        expect(yearBefore('2026-10-10')).toBe('2025-10-10');
    });
});

describe('formatPeriod', () => {
    it('names both days, or one when they are the same', () => {
        expect(formatPeriod({ from: '2026-10-01', to: '2026-10-10' })).toBe(
            'jue 1 oct al sáb 10 oct',
        );
        expect(formatPeriod({ from: '2026-10-10', to: '2026-10-10' })).toBe(
            'sáb 10 oct',
        );
    });
});

describe('ruleMode and describeRules', () => {
    const cut = { id: 1, name: 'Corte', percent: null, amount: null };

    it('lets a fixed amount win over a percent', () => {
        expect(ruleMode(cut)).toBe('general');
        expect(ruleMode({ ...cut, percent: 50 })).toBe('percent');
        expect(ruleMode({ ...cut, percent: 50, amount: '5.00' })).toBe(
            'amount',
        );
    });

    it('tells the general percent and how many services differ', () => {
        expect(describeRules(member({}))).toBe('40 % de cada servicio');
        expect(describeRules(member({ percent: 0 }))).toBe(
            'Sin comisión general',
        );
        expect(
            describeRules(
                member({
                    services: [
                        cut,
                        { ...cut, id: 2, amount: '5.00' },
                        { ...cut, id: 3, percent: 0 },
                    ],
                }),
            ),
        ).toBe('40 % de cada servicio · 2 servicios con regla propia');
    });
});

describe('pendingTotals', () => {
    it('adds only who is owed something, in whole céntimos', () => {
        expect(
            pendingTotals([
                member({ pendingCents: 1900, visits: 2 }),
                member({ id: 2, pendingCents: 10, visits: 1 }),
                member({ id: 3 }),
            ]),
        ).toEqual({ cents: 1910, visits: 3, people: 2 });
    });
});
