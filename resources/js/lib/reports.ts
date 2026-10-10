import type { Report, ReportDay } from '@/components/reports/types';
import { parseDay, shiftDay } from './commissions';
import { formatDate, formatMonth } from './format';

/** Up to a month every day gets its own bar. */
const MAX_DAILY_BUCKETS = 31;
/** Up to about a quarter, one bar per week; beyond that, per month. */
const MAX_WEEKLY_DAYS = 98;
const DAYS_PER_WEEK = 7;
const CENTS_PER_SOL = 100;
const TARGET_TICKS = 4;
const NICE_STEPS = [1, 2, 5, 10];
const SHORT_MONTHS = [
    'ene',
    'feb',
    'mar',
    'abr',
    'may',
    'jun',
    'jul',
    'ago',
    'set',
    'oct',
    'nov',
    'dic',
];

export type Grain = 'day' | 'week' | 'month';

/** Consecutive days added up: one bar pair of the chart. */
export type Bucket = {
    from: string;
    to: string;
    incomeCents: number;
    expensesCents: number;
};

export type Chart = { grain: Grain; buckets: Bucket[] };

export type Change = {
    /** Current minus previous, in céntimos. */
    deltaCents: number;
    /** Whole percent of the previous amount; null when there was none. */
    percent: number | null;
};

export function grainFor(dayCount: number): Grain {
    if (dayCount <= MAX_DAILY_BUCKETS) {
        return 'day';
    }

    return dayCount <= MAX_WEEKLY_DAYS ? 'week' : 'month';
}

/** What tells two days apart at each grain: the day, its Monday, its month. */
function bucketKey(date: string, grain: Grain): string {
    if (grain === 'day') {
        return date;
    }

    if (grain === 'month') {
        return date.slice(0, 7);
    }

    // getUTCDay() counts from Sunday; weeks here start on Monday.
    const daysSinceMonday =
        (parseDay(date).getUTCDay() + DAYS_PER_WEEK - 1) % DAYS_PER_WEEK;

    return shiftDay(date, -daysSinceMonday);
}

/**
 * The period's days as bars that stay readable: per day up to a month, per
 * week (Monday to Sunday) up to a quarter, per month after that. The first
 * and last bucket are cut where the period starts and ends.
 */
export function bucketDays(days: ReportDay[]): Chart {
    const grain = grainFor(days.length);
    const buckets: Bucket[] = [];
    let lastKey: string | null = null;

    for (const day of days) {
        const key = bucketKey(day.date, grain);
        const last = buckets.at(-1);

        if (last && key === lastKey) {
            last.to = day.date;
            last.incomeCents += day.incomeCents;
            last.expensesCents += day.expensesCents;
        } else {
            buckets.push({
                from: day.date,
                to: day.date,
                incomeCents: day.incomeCents,
                expensesCents: day.expensesCents,
            });
            lastKey = key;
        }
    }

    return { grain, buckets };
}

/**
 * Round steps for the amount axis, from 0 to the first one that covers the
 * biggest bar: 1, 2 or 5 times a power of ten, in whole soles.
 */
export function axisTicks(maxCents: number): number[] {
    const maxSoles = Math.max(Math.ceil(maxCents / CENTS_PER_SOL), 1);
    const magnitude = 10 ** Math.floor(Math.log10(maxSoles / TARGET_TICKS));
    const step = Math.max(
        1,
        magnitude *
            (NICE_STEPS.find(
                (nice) => nice * magnitude * TARGET_TICKS >= maxSoles,
            ) ?? 10),
    );
    const ticks = [0];

    while (ticks[ticks.length - 1] < maxSoles) {
        ticks.push(ticks[ticks.length - 1] + step);
    }

    return ticks.map((soles) => soles * CENTS_PER_SOL);
}

/** 150000 → "S/ 1500": the axis only shows whole soles. */
export function formatAxis(cents: number): string {
    return `S/ ${Math.round(cents / CENTS_PER_SOL)}`;
}

/** Under the bar: "7" for a day, "5 oct" for a week, "oct" for a month. */
export function bucketTick(bucket: Bucket, grain: Grain): string {
    const [, month, day] = bucket.from.split('-').map(Number);

    if (grain === 'day') {
        return String(day);
    }

    return grain === 'week'
        ? `${day} ${SHORT_MONTHS[month - 1]}`
        : SHORT_MONTHS[month - 1];
}

/** In full: "mié 7 oct", "lun 5 oct al dom 11 oct", "Octubre de 2026". */
export function bucketName(bucket: Bucket, grain: Grain): string {
    if (grain === 'month') {
        return formatMonth(bucket.from.slice(0, 7));
    }

    return bucket.from === bucket.to
        ? formatDate(bucket.from)
        : `${formatDate(bucket.from)} al ${formatDate(bucket.to)}`;
}

/** "2026-10-07" → "07/10/2026": with its year, which "mié 7 oct" leaves out. */
export function formatFullDate(date: string): string {
    const [year, month, day] = date.split('-');

    return `${day}/${month}/${year}`;
}

/** "Del 01/10/2026 al 10/10/2026", or the single day when both are the same. */
export function formatFullPeriod(from: string, to: string): string {
    return from === to
        ? formatFullDate(from)
        : `Del ${formatFullDate(from)} al ${formatFullDate(to)}`;
}

/** How an amount moved against the period before. */
export function compare(currentCents: number, previousCents: number): Change {
    const deltaCents = currentCents - previousCents;

    return {
        deltaCents,
        percent:
            previousCents === 0
                ? null
                : Math.round((deltaCents * 100) / Math.abs(previousCents)),
    };
}

/** Whole percent of a total; 0 when there is no total to take it from. */
export function shareOf(cents: number, totalCents: number): number {
    return totalCents <= 0 ? 0 : Math.round((cents * 100) / totalCents);
}

/** Nothing happened in the period: no visit, no expense, no money moved. */
export function isEmptyReport(report: Report): boolean {
    const { totals, lost, byMethod } = report;

    return (
        totals.visits === 0 &&
        totals.incomeCents === 0 &&
        totals.expensesCents === 0 &&
        lost.cancelled === 0 &&
        lost.noShows === 0 &&
        byMethod.every((method) => method.cents === 0)
    );
}
