import type {
    Period,
    ServiceRule,
    StaffCommission,
} from '@/components/commissions/types';
import { formatDate } from './format';

const MS_PER_DAY = 86_400_000;
const DAYS_PER_WEEK = 7;

/** How one service pays: like the rest, its own percent, or a fixed amount. */
export type RuleMode = 'general' | 'percent' | 'amount';

export type PeriodShortcut = Period & { label: string };

export type PendingTotals = { cents: number; visits: number; people: number };

/** Dates as UTC midnights: only the calendar matters, no timezone shifts it. */
function parseDay(day: string): Date {
    const [year, month, date] = day.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, date));
}

function toDay(date: Date): string {
    return date.toISOString().slice(0, 10);
}

/** "2026-10-10", -9 → "2026-10-01". */
export function shiftDay(day: string, by: number): string {
    return toDay(new Date(parseDay(day).getTime() + by * MS_PER_DAY));
}

/** "2026-10-10" → "2025-10-10": the longest period the server accepts. */
export function yearBefore(day: string): string {
    const date = parseDay(day);
    date.setUTCFullYear(date.getUTCFullYear() - 1);

    return toDay(date);
}

/** The usual periods, counted from today on the salon's calendar. */
export function periodShortcuts(today: string): PeriodShortcut[] {
    const monthStart = `${today.slice(0, 8)}01`;
    const lastMonthEnd = shiftDay(monthStart, -1);
    // Weeks start on Monday: getUTCDay() counts from Sunday.
    const daysSinceMonday =
        (parseDay(today).getUTCDay() + DAYS_PER_WEEK - 1) % DAYS_PER_WEEK;

    return [
        { label: 'Este mes', from: monthStart, to: today },
        {
            label: 'Mes pasado',
            from: `${lastMonthEnd.slice(0, 8)}01`,
            to: lastMonthEnd,
        },
        {
            label: 'Esta semana',
            from: shiftDay(today, -daysSinceMonday),
            to: today,
        },
    ];
}

/** "mié 1 oct al vie 10 oct", or a single day when both are the same. */
export function formatPeriod({ from, to }: Period): string {
    return from === to
        ? formatDate(from)
        : `${formatDate(from)} al ${formatDate(to)}`;
}

export function hasOwnRule(service: ServiceRule): boolean {
    return service.percent !== null || service.amount !== null;
}

/** A fixed amount wins over a percent, as on the server. */
export function ruleMode(service: ServiceRule): RuleMode {
    if (service.amount !== null) {
        return 'amount';
    }

    return service.percent === null ? 'general' : 'percent';
}

/** "40 % de cada servicio · 2 servicios con regla propia". */
export function describeRules(staff: StaffCommission): string {
    const own = staff.services.filter(hasOwnRule).length;
    const general =
        staff.percent === 0
            ? 'Sin comisión general'
            : `${staff.percent} % de cada servicio`;

    if (own === 0) {
        return general;
    }

    return `${general} · ${own === 1 ? '1 servicio con regla propia' : `${own} servicios con regla propia`}`;
}

/** 1 → "1 cita", 3 → "3 citas". */
export function countVisits(visits: number): string {
    return visits === 1 ? '1 cita' : `${visits} citas`;
}

/** What the whole team is owed in the period, in whole céntimos. */
export function pendingTotals(staff: StaffCommission[]): PendingTotals {
    const owed = staff.filter((member) => member.pendingCents > 0);

    return {
        cents: owed.reduce((total, member) => total + member.pendingCents, 0),
        visits: owed.reduce((total, member) => total + member.visits, 0),
        people: owed.length,
    };
}
