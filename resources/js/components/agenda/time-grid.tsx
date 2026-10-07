import type { AgendaBooking } from '@/components/booking/types';
import type { GridColumn } from '@/hooks/use-grid-drag';
import { useGridDrag } from '@/hooks/use-grid-drag';
import { formatTime } from '@/lib/format';
import { endMinutes, placeInLanes, toMinutes, toTime } from '@/lib/time-grid';
import { cn } from '@/lib/utils';
import { GridBlock } from './grid-block';

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
        <div className="overflow-x-auto rounded-xl border bg-card">
            <div className="min-w-[44rem]">
                <div className="sticky top-0 z-10 flex border-b bg-card">
                    <div className="w-16 shrink-0" />
                    {columns.map((column) => (
                        <div
                            key={column.key}
                            className={cn(
                                'min-w-0 flex-1 border-l px-2 py-2 text-center text-sm font-medium',
                                column.isCurrent && 'text-primary',
                            )}
                        >
                            <span className="block truncate capitalize">
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
                    <div className="relative w-16 shrink-0" aria-hidden>
                        {hourMarks.map((hour) => (
                            <span
                                key={hour}
                                className="absolute right-2 -translate-y-1/2 text-[11px] text-muted-foreground tabular-nums first:translate-y-0"
                                style={{ top: top(hour * 60) }}
                            >
                                {formatTime(toTime(hour * 60))}
                            </span>
                        ))}
                    </div>
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
                            <div
                                key={column.key}
                                role="group"
                                aria-label={column.label}
                                className={cn(
                                    'relative min-w-0 flex-1 border-l',
                                    column.isCurrent && 'bg-primary/5',
                                )}
                            >
                                {target?.column === column.key && (
                                    <span
                                        aria-hidden
                                        className={cn(
                                            'absolute inset-x-0.5 rounded-md border-2 border-dashed',
                                            target.conflict
                                                ? 'border-destructive bg-destructive/10'
                                                : 'border-primary bg-primary/10',
                                        )}
                                        style={{
                                            top: top(target.start),
                                            height:
                                                (target.end - target.start) *
                                                PIXELS_PER_MINUTE,
                                        }}
                                    />
                                )}
                                {placeInLanes(
                                    visible
                                        .filter(
                                            (booking) =>
                                                columnOf(booking) ===
                                                column.key,
                                        )
                                        .map((booking) => ({
                                            item: booking,
                                            start: toMinutes(booking.time),
                                            end: endMinutes(
                                                booking.time,
                                                booking.endTime,
                                            ),
                                        })),
                                ).map((placed) => (
                                    <GridBlock
                                        key={placed.item.id}
                                        placed={placed}
                                        gridStart={range.from}
                                        pixelsPerMinute={PIXELS_PER_MINUTE}
                                        draggable={
                                            canDrag &&
                                            placed.item.actions.includes('move')
                                        }
                                        onOpen={() => onOpen(placed.item)}
                                        onDrag={(offset) =>
                                            drag(placed.item, offset)
                                        }
                                        onDrop={(offset) =>
                                            drop(placed.item, offset)
                                        }
                                    />
                                ))}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}
