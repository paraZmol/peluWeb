import { fromCents, toCents } from './money';

/** "35.50" → "S/ 35.50". Amounts arrive as text from the server (no floats). */
export function formatPrice(amount: string): string {
    return `S/ ${amount}`;
}

/** 45 → "45 min", 60 → "1 h", 90 → "1 h 30 min". */
export function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return `${rest} min`;
    }

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}

/** "15:30" → "3:30 p. m." (24 h text from the server to how people read it). */
export function formatTime(time: string): string {
    const [hours, minutes] = time.split(':').map(Number);
    const hours12 = hours % 12 === 0 ? 12 : hours % 12;

    return `${hours12}:${String(minutes).padStart(2, '0')} ${hours < 12 ? 'a. m.' : 'p. m.'}`;
}

const dayFormat = new Intl.DateTimeFormat('es-PE', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
});

/**
 * "2026-10-14" → "mié 14 oct" (the browser adds dots and a comma that only
 * add noise). Built from parts so no timezone shifts it.
 */
export function formatDate(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return dayFormat
        .format(new Date(year, month - 1, day))
        .replace(/[.,]/g, '')
        .toLowerCase();
}

/** "2026-10-07 15:30" → "mié 7 oct, 3:30 p. m." (already on the salon's clock). */
export function formatDateTime(dateTime: string): string {
    const [date, time] = dateTime.split(' ');

    return `${formatDate(date)}, ${formatTime(time)}`;
}

/** Adds amounts sent as text ("35.50") in whole céntimos, never as floats. */
export function sumAmounts(amounts: string[]): string {
    return fromCents(
        amounts.reduce((total, amount) => total + (toCents(amount) ?? 0), 0),
    );
}

/** 2450 → "S/ 24.50". */
export function formatCents(cents: number): string {
    return formatPrice(fromCents(cents));
}

const monthFormat = new Intl.DateTimeFormat('es-PE', {
    month: 'long',
    year: 'numeric',
});

/** "2026-10" → "Octubre de 2026". */
export function formatMonth(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const text = monthFormat.format(new Date(year, monthNumber - 1, 1));

    return text.charAt(0).toUpperCase() + text.slice(1);
}
