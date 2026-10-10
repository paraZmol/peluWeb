/** What the server accepts as an amount: up to 6 digits and 2 decimals. */
const AMOUNT = /^(\d{1,6})(?:[.,](\d{1,2}))?$/;

/**
 * "24.5" → 2450, "24,50" → 2450, "" → 0 (nothing typed yet). Null when the
 * text is not an amount. Read digit by digit: soles never pass through a
 * float, so 0.1 + 0.2 cannot happen.
 */
export function toCents(amount: string): number | null {
    const text = amount.trim();

    if (text === '') {
        return 0;
    }

    const match = AMOUNT.exec(text);

    if (!match) {
        return null;
    }

    return Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'));
}

/** 2450 → "24.50", the text the server sends and expects. */
export function fromCents(cents: number): string {
    const absolute = Math.abs(cents);
    const sign = cents < 0 ? '-' : '';

    return `${sign}${Math.floor(absolute / 100)}.${String(absolute % 100).padStart(2, '0')}`;
}
