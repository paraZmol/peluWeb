import type { CashMethod } from '@/components/cash/types';
import { formatCents } from './format';
import { toCents } from './money';

/** The only method that lives in the drawer; the rest is checked elsewhere. */
export const CASH_METHOD = 'cash';

/** How a count compares with what the drawer should hold. */
export type CashTone = 'even' | 'short' | 'over';

export type CashDifference = { tone: CashTone; label: string };

/**
 * "-12.50" → -1250. The expected cash is the only amount that travels with
 * a sign (more can leave the drawer than it ever held).
 */
export function signedCents(amount: string): number {
    const isNegative = amount.startsWith('-');
    const cents = toCents(isNegative ? amount.slice(1) : amount) ?? 0;

    return isNegative ? -cents : cents;
}

/** -300 → "− S/ 3.00": the sign before the currency, as people write it. */
export function formatBalance(cents: number): string {
    return cents < 0 ? `− ${formatCents(-cents)}` : formatCents(cents);
}

/**
 * Counted minus expected, in words: the color that goes with it is never
 * the only cue. `isClosed` tells it in the past, for the history.
 */
export function describeDifference(
    cents: number,
    isClosed = false,
): CashDifference {
    if (cents === 0) {
        return { tone: 'even', label: isClosed ? 'Cuadró' : 'Cuadra' };
    }

    if (cents < 0) {
        return {
            tone: 'short',
            label: `${isClosed ? 'Faltaron' : 'Faltan'} ${formatCents(-cents)}`,
        };
    }

    return {
        tone: 'over',
        label: `${isClosed ? 'Sobraron' : 'Sobran'} ${formatCents(cents)}`,
    };
}

/** The drawer's own row apart from the methods checked in an app or a bank. */
export function splitMethods(methods: CashMethod[]): {
    cash: CashMethod | undefined;
    others: CashMethod[];
} {
    return {
        cash: methods.find((method) => method.value === CASH_METHOD),
        others: methods.filter((method) => method.value !== CASH_METHOD),
    };
}
