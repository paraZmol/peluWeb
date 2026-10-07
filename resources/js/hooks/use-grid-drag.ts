import { useRef, useState } from 'react';
import type { AgendaBooking } from '@/components/booking/types';
import { endMinutes, snap, toMinutes, toTime } from '@/lib/time-grid';

export type GridColumn = {
    key: string;
    label: string;
    hint?: string;
    isCurrent?: boolean;
};

type Options = {
    columns: GridColumn[];
    bookings: AgendaBooking[];
    columnOf: (booking: AgendaBooking) => string;
    /** Minutes since midnight where the grid starts and ends. */
    range: { from: number; to: number };
    step: number;
    pixelsPerMinute: number;
    /** Whether a visit may be dropped on another column. */
    canChangeColumn: boolean;
    onMove: (booking: AgendaBooking, column: string, time: string) => void;
};

export type DropTarget = {
    column: string;
    start: number;
    end: number;
    /** Another visit already takes that time: the server would refuse it. */
    conflict: boolean;
};

type Offset = { x: number; y: number };

/**
 * Turns how far a visit was dragged into where it would land: which
 * column, at what time (snapped to the salon's grid), and whether it would
 * sit on another visit. Only a hint: the server decides when it is dropped.
 */
export function useGridDrag({
    columns,
    bookings,
    columnOf,
    range,
    step,
    pixelsPerMinute,
    canChangeColumn,
    onMove,
}: Options) {
    const area = useRef<HTMLDivElement>(null);
    const [target, setTarget] = useState<DropTarget | null>(null);

    const locate = (booking: AgendaBooking, offset: Offset): DropTarget => {
        const start = toMinutes(booking.time);
        const length = endMinutes(booking.time, booking.endTime) - start;
        const from = columns.findIndex((c) => c.key === columnOf(booking));
        const width =
            (area.current?.getBoundingClientRect().width ?? 0) / columns.length;
        const shift =
            canChangeColumn && width > 0 ? Math.round(offset.x / width) : 0;
        const column =
            columns[Math.min(Math.max(from + shift, 0), columns.length - 1)];
        const moved = snap(start + offset.y / pixelsPerMinute, step);
        const landed = Math.min(Math.max(moved, range.from), range.to - length);

        return {
            column: column.key,
            start: landed,
            end: landed + length,
            conflict: bookings.some(
                (other) =>
                    other.id !== booking.id &&
                    columnOf(other) === column.key &&
                    toMinutes(other.time) < landed + length &&
                    endMinutes(other.time, other.endTime) > landed,
            ),
        };
    };

    return {
        area,
        target,
        drag: (booking: AgendaBooking, offset: Offset) =>
            setTarget(locate(booking, offset)),
        drop: (booking: AgendaBooking, offset: Offset) => {
            const landed = locate(booking, offset);
            setTarget(null);

            const stayed =
                landed.column === columnOf(booking) &&
                landed.start === toMinutes(booking.time);

            if (!stayed) {
                onMove(booking, landed.column, toTime(landed.start));
            }
        },
    };
}
