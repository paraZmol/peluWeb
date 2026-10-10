import type { Expense } from '@/components/expenses/types';

/** How expenses without a category are named everywhere. */
export const UNCATEGORIZED = 'Sin categoría';

export type CategoryShare = {
    /** The category's id, or "none" for the uncategorized ones. */
    key: string;
    name: string;
    cents: number;
    /** Whole percent of the month's total. */
    percent: number;
};

export type ExpenseDay = { date: string; cents: number; expenses: Expense[] };

/** "2026-01", -1 → "2025-12". */
export function shiftMonth(month: string, by: number): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const index = year * 12 + (monthNumber - 1) + by;

    return `${Math.floor(index / 12)}-${String((index % 12) + 1).padStart(2, '0')}`;
}

/** Whole céntimos added up: amounts never pass through a float. */
export function totalCents(expenses: Expense[]): number {
    return expenses.reduce((total, expense) => total + expense.amountCents, 0);
}

/** What each category took of the month, biggest first. */
export function breakdownByCategory(expenses: Expense[]): CategoryShare[] {
    const total = totalCents(expenses);
    const shares = new Map<string, CategoryShare>();

    for (const expense of expenses) {
        const key = String(expense.categoryId ?? 'none');
        const share = shares.get(key) ?? {
            key,
            name: expense.categoryName ?? UNCATEGORIZED,
            cents: 0,
            percent: 0,
        };

        share.cents += expense.amountCents;
        shares.set(key, share);
    }

    return [...shares.values()]
        .map((share) => ({
            ...share,
            percent: total === 0 ? 0 : Math.round((share.cents * 100) / total),
        }))
        .sort((a, b) => b.cents - a.cents);
}

/** Consecutive expenses of the same day together, in the order received. */
export function groupByDay(expenses: Expense[]): ExpenseDay[] {
    const days: ExpenseDay[] = [];

    for (const expense of expenses) {
        const last = days.at(-1);

        if (last?.date === expense.date) {
            last.expenses.push(expense);
            last.cents += expense.amountCents;
        } else {
            days.push({
                date: expense.date,
                cents: expense.amountCents,
                expenses: [expense],
            });
        }
    }

    return days;
}
