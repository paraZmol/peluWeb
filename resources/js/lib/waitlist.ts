import type { BookingDay } from '@/components/booking/types';

/** A request that never reached the server. */
export const NETWORK_PROBLEM =
    'No se pudo enviar. Revisa tu conexión y vuelve a intentarlo.';

/**
 * How the server's refusals are told apart (they arrive as the text of a
 * toast, see `WaitlistException`): already on that day's list counts as done,
 * and a day that does have times asks for them again.
 */
export const ALREADY_WAITING = 'Ya estás en la lista';
export const THERE_ARE_TIMES = 'horarios libres';

/**
 * The day a link asks the wizard to open on ("?fecha=2026-10-08", sent with
 * the notice that a time opened up), if it is one that can be picked.
 */
export function askedDay(search: string, days: BookingDay[]): string | null {
    const asked = new URLSearchParams(search).get('fecha');
    const day = days.find((candidate) => candidate.date === asked);

    return day && !day.closed ? day.date : null;
}
