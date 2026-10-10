import { describe, expect, it } from 'vite-plus/test';
import type { Expense } from '@/components/expenses/types';
import {
    breakdownByCategory,
    groupByDay,
    shiftMonth,
    totalCents,
} from './expenses';

function expense(overrides: Partial<Expense>): Expense {
    return {
        id: 1,
        date: '2026-10-07',
        amount: '10.00',
        amountCents: 1000,
        method: 'cash',
        methodLabel: 'Efectivo',
        categoryId: 1,
        categoryName: 'Alquiler',
        description: null,
        receiptUrl: null,
        isSettlement: false,
        ...overrides,
    };
}

describe('shiftMonth', () => {
    it('crosses the year in both directions', () => {
        expect(shiftMonth('2026-10', -1)).toBe('2026-09');
        expect(shiftMonth('2026-01', -1)).toBe('2025-12');
        expect(shiftMonth('2025-12', 1)).toBe('2026-01');
    });
});

describe('breakdownByCategory', () => {
    it('adds whole céntimos per category, biggest first', () => {
        const shares = breakdownByCategory([
            expense({ amountCents: 10 }),
            expense({ amountCents: 20 }),
            expense({ categoryId: 2, categoryName: 'Otros', amountCents: 70 }),
        ]);

        expect(shares.map((s) => [s.name, s.cents, s.percent])).toEqual([
            ['Otros', 70, 70],
            ['Alquiler', 30, 30],
        ]);
    });

    it('keeps the expenses without a category together', () => {
        const shares = breakdownByCategory([
            expense({ categoryId: null, categoryName: null }),
            expense({ categoryId: null, categoryName: null }),
        ]);

        expect(shares).toEqual([
            { key: 'none', name: 'Sin categoría', cents: 2000, percent: 100 },
        ]);
    });

    it('has nothing to show for an empty month', () => {
        expect(breakdownByCategory([])).toEqual([]);
        expect(totalCents([])).toBe(0);
    });
});

describe('groupByDay', () => {
    it('groups by day with the day total, keeping the order', () => {
        const days = groupByDay([
            expense({ id: 3, date: '2026-10-07', amountCents: 150 }),
            expense({ id: 2, date: '2026-10-07', amountCents: 50 }),
            expense({ id: 1, date: '2026-10-01', amountCents: 999 }),
        ]);

        expect(
            days.map((d) => [d.date, d.cents, d.expenses.map((e) => e.id)]),
        ).toEqual([
            ['2026-10-07', 200, [3, 2]],
            ['2026-10-01', 999, [1]],
        ]);
    });
});
