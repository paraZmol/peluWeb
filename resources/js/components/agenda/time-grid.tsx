import type { AgendaBooking } from '@/components/booking/types';
import type { GridColumn } from '@/hooks/use-grid-drag';
import { useGridDrag } from '@/hooks/use-grid-drag';
import { cn } from '@/lib/utils';
import { TimeGridColumn } from './time-grid-column';
import { TimeGridHours } from './time-grid-hours';

type Props = {
    columns: GridColumn[];
    bookings: AgendaBooking[];
    columnOf: (booking: AgendaBooking) => string;
    /** First and last hour shown (0–24). */
    hours: { from: number; to: number };
    /** The salon's grid in minutes: drops snap to it. */
    step: number;
    canChangeColumn: boolean;
    /** Dragging needs a mouse; on touch screens the page must scroll. */
    canDrag: boolean;
    onMove: (booking: AgendaBooking, column: string, time: string) => void;
    onOpen: (booking: AgendaBooking) => void;
};

const PIXELS_PER_MINUTE = 1.2;
/** Visits that no longer take anyone's time are left out of the grid. */
const HIDDEN = ['cancelled', 'no_show', 'expired'];
/** Width of the hours gutter and the least a column needs to be read. */
const GUTTER = 'w-14 shrink-0';
const COLUMN_MIN_REM = 7.5;
const GUTTER_REM = 3.5;

/** Visits laid out on a clock: one column per day or per professional. */
export function TimeGrid({
    columns,
    bookings,
    columnOf,
    hours,
    step,
    canChangeColumn,
    canDrag,
    onMove,
    onOpen,
}: Props) {
    const range = { from: hours.from * 60, to: hours.to * 60 };
    const visible = bookings.filter(
        (booking) => !HIDDEN.includes(booking.status),
    );
    const { area, target, drag, drop } = useGridDrag({
        columns,
        bookings: visible,
        columnOf,
        range,
        step,
        pixelsPerMinute: PIXELS_PER_MINUTE,
        canChangeColumn,
        onMove,
    });
    const hourMarks = Array.from(
        { length: hours.to - hours.from },
        (_, index) => hours.from + index,
    );
    const top = (minutes: number) => (minutes - range.from) * PIXELS_PER_MINUTE;

    return (
        // Scrolls both ways inside itself, so names and hours stay in view.
        <div className="max-h-[75svh] overflow-auto overscroll-x-contain rounded-xl border bg-card">
            <div
                style={{
                    minWidth: `${GUTTER_REM + columns.length * COLUMN_MIN_REM}rem`,
                }}
            >
                <div className="sticky top-0 z-20 flex border-b bg-card">
                    <div className={cn(GUTTER, 'sticky left-0 z-10 bg-card')} />
                    {columns.map((column) => (
                        <div
                            key={column.key}
                            className={cn(
                                'min-w-0 flex-1 border-l px-2 py-2.5 text-center text-sm font-medium',
                                column.isCurrent && 'bg-primary/5',
                            )}
                        >
                            <span
                                className={cn(
                                    'block truncate capitalize',
                                    // Today wears the brand, readable in both themes.
                                    column.isCurrent &&
                                        'mx-auto w-fit max-w-full rounded-full bg-primary px-2.5 text-primary-foreground',
                                )}
                            >
                                {column.label}
                            </span>
                            {column.hint && (
                                <span className="block truncate text-xs font-normal text-muted-foreground">
                                    {column.hint}
                                </span>
                            )}
                        </div>
                    ))}
                </div>

                <div
                    className="relative flex"
                    style={{ height: top(range.to) }}
                >
                    <TimeGridHours
                        className={cn(GUTTER, 'sticky left-0 z-10 bg-card')}
                        hours={hourMarks}
                        top={top}
                        target={target}
                    />
                    <div ref={area} className="relative flex flex-1">
                        {hourMarks.map((hour) => (
                            <span
                                key={hour}
                                aria-hidden
                                className="pointer-events-none absolute inset-x-0 border-t border-border/60"
                                style={{ top: top(hour * 60) }}
                            />
                        ))}
                        {columns.map((column) => (
                            <TimeGridColumn
                                key={column.key}
                                column={column}
                                bookings={visible.filter(
                                    (booking) =>
                                        columnOf(booking) === column.key,
                                )}
                                target={target}
                                gridStart={range.from}
                                pixelsPerMinute={PIXELS_PER_MINUTE}
                                canDrag={canDrag}
                                onOpen={onOpen}
                                onDrag={drag}
                                onDrop={drop}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
