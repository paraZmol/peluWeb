import { formatTime } from './format';

/*
 | When a promotion holds, said short for a card or a line under a service.
 | The server sends it written out ("lunes, martes y miércoles, de 10:00 a
 | 16:00"), the way its conditions say it; here it becomes "lun a mié,
 | 10:00 a. m. a 4:00 p. m.". Anything it cannot read is shown as it came.
 */

const DAYS = [
    'lunes',
    'martes',
    'miércoles',
    'jueves',
    'viernes',
    'sábado',
    'domingo',
];
const SHORT_DAYS = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
const WEEK = DAYS.length;
/** From three days in a row on, a range reads better than a list. */
const MIN_RANGE = 3;
const HOURS = /^de (\d{1,2}:\d{2}) a (\d{1,2}:\d{2})$/;

/** "A", "A y B", "A, B y C". */
function joinList(items: string[]): string {
    return items.length <= 1
        ? (items[0] ?? '')
        : `${items.slice(0, -1).join(', ')} y ${items[items.length - 1]}`;
}

/** Day numbers (0 = Monday) of "lunes, martes y viernes"; null if unreadable. */
function readDays(text: string): number[] | null {
    const days = text.split(/, | y /).map((day) => DAYS.indexOf(day.trim()));

    return days.every((day) => day >= 0) ? days : null;
}

/** [0, 1, 2, 4] → "lun a mié y vie". */
function shortDays(days: number[]): string {
    const sorted = [...new Set(days)].sort((a, b) => a - b);
    const runs: number[][] = [];

    for (const day of sorted) {
        const run = runs[runs.length - 1];

        if (run && run[run.length - 1] === day - 1) {
            run.push(day);
        } else {
            runs.push([day]);
        }
    }

    return joinList(
        runs.flatMap((run) =>
            run.length >= MIN_RANGE
                ? [`${SHORT_DAYS[run[0]]} a ${SHORT_DAYS[run[run.length - 1]]}`]
                : run.map((day) => SHORT_DAYS[day]),
        ),
    );
}

/** "15:00", "19:00" → "3:00 a 7:00 p. m."; one suffix when both share it. */
function shortHours(from: string, to: string): string {
    const start = formatTime(from);
    const end = formatTime(to);
    const suffix = end.slice(end.indexOf(' '));

    return start.endsWith(suffix)
        ? `${start.slice(0, -suffix.length)} a ${end}`
        : `${start} a ${end}`;
}

/**
 * "lunes, martes, miércoles, jueves y viernes, de 15:00 a 19:00" →
 * "lun a vie, 3:00 a 7:00 p. m."; every day at any hour → null (nothing to
 * say). Only hours → "de 3:00 a 7:00 p. m.".
 */
export function shortWhen(when: string | null): string | null {
    if (when === null) {
        return null;
    }

    const hoursAt = when.search(/(^|, )de \d/);
    const daysText = hoursAt === -1 ? when : when.slice(0, hoursAt);
    const hoursText =
        hoursAt === -1 ? '' : when.slice(hoursAt).replace(/^, /, '');
    const days = daysText === '' ? [] : readDays(daysText);
    const hours = hoursText === '' ? null : HOURS.exec(hoursText);

    if (days === null || (hoursText !== '' && hours === null)) {
        return when;
    }

    const isEveryDay = days.length === 0 || new Set(days).size === WEEK;
    const dayPart = isEveryDay ? null : shortDays(days);
    const hourPart = hours ? shortHours(hours[1], hours[2]) : null;

    if (dayPart && hourPart) {
        return `${dayPart}, ${hourPart}`;
    }

    return dayPart ?? (hourPart ? `de ${hourPart}` : null);
}
