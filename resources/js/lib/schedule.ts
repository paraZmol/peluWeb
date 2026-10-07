/** ISO weekdays, Monday first, as the server stores them. */
export const WEEKDAYS = [
    { iso: 1, label: 'Lunes', short: 'Lun', initial: 'L' },
    { iso: 2, label: 'Martes', short: 'Mar', initial: 'M' },
    { iso: 3, label: 'Miércoles', short: 'Mié', initial: 'X' },
    { iso: 4, label: 'Jueves', short: 'Jue', initial: 'J' },
    { iso: 5, label: 'Viernes', short: 'Vie', initial: 'V' },
    { iso: 6, label: 'Sábado', short: 'Sáb', initial: 'S' },
    { iso: 7, label: 'Domingo', short: 'Dom', initial: 'D' },
] as const;

export function weekdayLabel(iso: number): string {
    return WEEKDAYS.find((day) => day.iso === iso)?.label ?? '';
}

/** "09:30" → 570 */
export function toMinutes(time: string): number {
    const [hours, minutes] = time.split(':').map(Number);

    return hours * 60 + minutes;
}

/** 570 → "09:30" */
export function toTime(minutes: number): string {
    return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

type Span = { start: string; end: string };

/** Ranges that only touch (one ends when the other starts) do not overlap. */
export function overlaps(a: Span, b: Span): boolean {
    return (
        toMinutes(a.start) < toMinutes(b.end) &&
        toMinutes(b.start) < toMinutes(a.end)
    );
}
