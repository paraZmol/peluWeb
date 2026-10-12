import { describe, expect, it } from 'vite-plus/test';
import type { Report, ReportDay } from '@/components/reports/types';
import { shiftDay } from './commissions';
import {
    axisTicks,
    bucketDays,
    bucketName,
    bucketTick,
    compare,
    formatAxis,
    formatFullDate,
    formatFullPeriod,
    grainFor,
    isEmptyReport,
    shareOf,
} from './reports';

/** `count` days in a row from `from`, each with the same amounts. */
const days = (from: string, count: number, incomeCents = 100): ReportDay[] =>
    Array.from({ length: count }, (_, index) => ({
        date: shiftDay(from, index),
        incomeCents,
        expensesCents: index === 0 ? 50 : 0,
    }));

const totals = {
    incomeCents: 0,
    servicesCents: 0,
    keptDepositsCents: 0,
    expensesCents: 0,
    profitCents: 0,
    discountsCents: 0,
    promotionsCents: 0,
    visits: 0,
};

const emptyReport: Report = {
    totals,
    previous: totals,
    days: [],
    byCategory: [],
    byStaff: [],
    byService: [],
    byMethod: [],
    lost: { cancelled: 0, cancelledCents: 0, noShows: 0, noShowCents: 0 },
    clients: { new: 0, returning: 0 },
    occupancy: [],
};

describe('grainFor', () => {
    it('goes by day up to a month, by week up to a quarter, then by month', () => {
        expect(grainFor(1)).toBe('day');
        expect(grainFor(31)).toBe('day');
        expect(grainFor(32)).toBe('week');
        expect(grainFor(98)).toBe('week');
        expect(grainFor(99)).toBe('month');
        expect(grainFor(366)).toBe('month');
    });
});

describe('bucketDays', () => {
    it('keeps a single day as one bucket', () => {
        expect(bucketDays(days('2026-10-10', 1))).toEqual({
            grain: 'day',
            buckets: [
                {
                    from: '2026-10-10',
                    to: '2026-10-10',
                    incomeCents: 100,
                    expensesCents: 50,
                },
            ],
        });
    });

    it('keeps one bucket per day for a whole month, also the empty days', () => {
        const chart = bucketDays(days('2026-10-01', 31, 0));

        expect(chart.grain).toBe('day');
        expect(chart.buckets).toHaveLength(31);
        expect(chart.buckets[30]).toMatchObject({
            from: '2026-10-31',
            incomeCents: 0,
        });
    });

    it('groups by weeks from Monday, cutting the first and the last one', () => {
        // 2026-09-02 is a Wednesday; 40 days end on Sunday 2026-10-11.
        const { grain, buckets } = bucketDays(days('2026-09-02', 40));

        expect(grain).toBe('week');
        expect(buckets).toHaveLength(6);
        expect(buckets[0]).toEqual({
            from: '2026-09-02',
            to: '2026-09-06',
            incomeCents: 500,
            expensesCents: 50,
        });
        expect(buckets[1]).toMatchObject({
            from: '2026-09-07',
            to: '2026-09-13',
            incomeCents: 700,
            expensesCents: 0,
        });
        expect(buckets[5]).toMatchObject({
            from: '2026-10-05',
            to: '2026-10-11',
        });
    });

    it('groups a year by calendar month and loses no céntimo', () => {
        // The longest period the server sends: a year and a day.
        const year = days('2025-10-10', 366);
        const { grain, buckets } = bucketDays(year);

        expect(grain).toBe('month');
        expect(buckets).toHaveLength(13);
        expect(buckets[0]).toMatchObject({
            from: '2025-10-10',
            to: '2025-10-31',
            incomeCents: 2200,
        });
        expect(buckets[12]).toMatchObject({
            from: '2026-10-01',
            to: '2026-10-10',
        });
        expect(
            buckets.reduce((total, bucket) => total + bucket.incomeCents, 0),
        ).toBe(36600);
        expect(
            buckets.reduce((total, bucket) => total + bucket.expensesCents, 0),
        ).toBe(50);
    });

    it('returns no buckets without days', () => {
        expect(bucketDays([])).toEqual({ grain: 'day', buckets: [] });
    });
});

describe('axisTicks', () => {
    it('covers the biggest bar with round steps in whole soles', () => {
        expect(axisTicks(3500)).toEqual([0, 1000, 2000, 3000, 4000]);
        expect(axisTicks(12050)).toEqual([0, 5000, 10000, 15000]);
        expect(axisTicks(150000)).toEqual([0, 50000, 100000, 150000]);
    });

    it('never goes below one sol, even with nothing to show', () => {
        expect(axisTicks(0)).toEqual([0, 100]);
        expect(axisTicks(50)).toEqual([0, 100]);
        expect(axisTicks(250)).toEqual([0, 100, 200, 300]);
    });
});

describe('labels', () => {
    const bucket = {
        from: '2026-10-05',
        to: '2026-10-11',
        incomeCents: 0,
        expensesCents: 0,
    };

    it('names a bucket under its bar', () => {
        expect(bucketTick(bucket, 'day')).toBe('5');
        expect(bucketTick(bucket, 'week')).toBe('5 oct');
        expect(bucketTick(bucket, 'month')).toBe('oct');
    });

    it('names a bucket in full', () => {
        expect(bucketName({ ...bucket, to: bucket.from }, 'day')).toBe(
            'lun 5 oct',
        );
        expect(bucketName(bucket, 'week')).toBe('lun 5 oct al dom 11 oct');
        expect(bucketName(bucket, 'month')).toBe('Octubre de 2026');
    });

    it('writes the axis in whole soles', () => {
        expect(formatAxis(150000)).toBe('S/ 1500');
        expect(formatAxis(0)).toBe('S/ 0');
    });

    it('writes a day with its year', () => {
        expect(formatFullDate('2026-10-07')).toBe('07/10/2026');
        expect(formatFullPeriod('2025-10-10', '2026-10-09')).toBe(
            'Del 10/10/2025 al 09/10/2026',
        );
        expect(formatFullPeriod('2026-10-09', '2026-10-09')).toBe('09/10/2026');
    });
});

describe('compare', () => {
    it('tells the difference and its percent of the period before', () => {
        expect(compare(12000, 10000)).toEqual({
            deltaCents: 2000,
            percent: 20,
        });
        expect(compare(5000, 10000)).toEqual({
            deltaCents: -5000,
            percent: -50,
        });
    });

    it('has no percent when the period before had nothing', () => {
        expect(compare(3500, 0)).toEqual({ deltaCents: 3500, percent: null });
    });

    it('measures against the size of a loss, not its sign', () => {
        // From losing S/ 100 to earning S/ 50: S/ 150 better.
        expect(compare(5000, -10000)).toEqual({
            deltaCents: 15000,
            percent: 150,
        });
    });
});

describe('shareOf', () => {
    it('gives the whole percent, and 0 without a total', () => {
        expect(shareOf(3500, 10000)).toBe(35);
        expect(shareOf(1, 3)).toBe(33);
        expect(shareOf(100, 0)).toBe(0);
    });
});

describe('isEmptyReport', () => {
    it('is empty when nothing moved', () => {
        expect(isEmptyReport(emptyReport)).toBe(true);
    });

    it('is not empty with an expense, a lost visit or money received', () => {
        expect(
            isEmptyReport({
                ...emptyReport,
                totals: { ...totals, expensesCents: 100, profitCents: -100 },
            }),
        ).toBe(false);
        expect(
            isEmptyReport({
                ...emptyReport,
                lost: { ...emptyReport.lost, noShows: 1 },
            }),
        ).toBe(false);
        expect(
            isEmptyReport({
                ...emptyReport,
                byMethod: [{ value: 'yape', label: 'Yape', cents: 500 }],
            }),
        ).toBe(false);
    });
});
