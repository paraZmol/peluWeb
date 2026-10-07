import { useState } from 'react';
import type { ScheduleRange } from '@/components/schedule/types';
import { toMinutes, toTime } from '@/lib/schedule';

export const MAX_RANGES_PER_DAY = 4;

const DEFAULT_START = '09:00';
const DEFAULT_END = '18:00';
const GAP_AFTER_LAST = 60;
const DEFAULT_LENGTH = 240;
const LAST_START = 22 * 60;
const DAY_END = 23 * 60;

function sorted(ranges: ScheduleRange[]): ScheduleRange[] {
    return [...ranges].sort(
        (a, b) => a.weekday - b.weekday || a.start.localeCompare(b.start),
    );
}

function isSame(a: ScheduleRange, b: ScheduleRange): boolean {
    return a.weekday === b.weekday && a.start === b.start && a.end === b.end;
}

/**
 * The week being edited, kept sorted like the server returns it so
 * "has unsaved changes" is a plain comparison.
 */
export function useWeeklySchedule(saved: ScheduleRange[]) {
    const [ranges, setRanges] = useState(() => sorted(saved));

    const ofDay = (weekday: number) =>
        ranges.filter((range) => range.weekday === weekday);

    /** A sensible next range: 9–6 on an empty day, else after the last one. */
    const suggest = (weekday: number): ScheduleRange | null => {
        const last = ofDay(weekday).at(-1);

        if (!last) {
            return {
                weekday,
                start: DEFAULT_START,
                end: DEFAULT_END,
                accessLevel: 'public',
            };
        }

        const start = toMinutes(last.end) + GAP_AFTER_LAST;

        if (start > LAST_START) {
            return null;
        }

        return {
            weekday,
            start: toTime(start),
            end: toTime(Math.min(start + DEFAULT_LENGTH, DAY_END)),
            accessLevel: 'public',
        };
    };

    const replace = (previous: ScheduleRange | null, next: ScheduleRange) =>
        setRanges((current) =>
            sorted([
                ...current.filter((r) => !previous || !isSame(r, previous)),
                next,
            ]),
        );

    const remove = (range: ScheduleRange) =>
        setRanges((current) => current.filter((r) => !isSame(r, range)));

    const copyDay = (from: number, weekdays: number[]) =>
        setRanges((current) => {
            const source = current.filter((r) => r.weekday === from);
            const targets = weekdays.filter((weekday) => weekday !== from);

            return sorted([
                ...current.filter((r) => !targets.includes(r.weekday)),
                ...targets.flatMap((weekday) =>
                    source.map((range) => ({ ...range, weekday })),
                ),
            ]);
        });

    return {
        ranges,
        ofDay,
        suggest,
        replace,
        remove,
        copyDay,
        isDirty: JSON.stringify(ranges) !== JSON.stringify(sorted(saved)),
        reset: () => setRanges(sorted(saved)),
    };
}
