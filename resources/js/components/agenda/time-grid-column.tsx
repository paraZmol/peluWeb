import type { PanInfo } from 'motion/react';
import type { AgendaBooking } from '@/components/booking/types';
import type { DropTarget, GridColumn } from '@/hooks/use-grid-drag';
import { endMinutes, placeInLanes, toMinutes } from '@/lib/time-grid';
import { cn } from '@/lib/utils';
import { GridBlock } from './grid-block';

type Offset = PanInfo['offset'];

type Props = {
    column: GridColumn;
    /** Only the visits of this column. */
    bookings: AgendaBooking[];
    /** Where the visit being dragged would land, in any column. */
    target: DropTarget | null;
    /** Top of the grid in minutes since midnight, and its scale. */
    gridStart: number;
    pixelsPerMinute: number;
    canDrag: boolean;
    onOpen: (booking: AgendaBooking) => void;
    onDrag: (booking: AgendaBooking, offset: Offset) => void;
    onDrop: (booking: AgendaBooking, offset: Offset) => void;
};

/** One day or one professional of the time grid, with its visits in lanes. */
export function TimeGridColumn({
    column,
    bookings,
    target,
    gridStart,
    pixelsPerMinute,
    canDrag,
    onOpen,
    onDrag,
    onDrop,
}: Props) {
    const placed = placeInLanes(
        bookings.map((booking) => ({
            item: booking,
            start: toMinutes(booking.time),
            end: endMinutes(booking.time, booking.endTime),
        })),
    );

    return (
        <div
            role="group"
            aria-label={column.label}
            className={cn(
                'relative min-w-0 flex-1 border-l',
                column.isCurrent && 'bg-primary/5',
            )}
        >
            {/* The ghost: where the dragged visit lands, red if it is taken. */}
            {target?.column === column.key && (
                <span
                    aria-hidden
                    className={cn(
                        'absolute inset-x-0.5 rounded-[6px] border-2 border-dashed',
                        target.conflict
                            ? 'border-destructive bg-destructive/10'
                            : 'border-primary bg-primary/10',
                    )}
                    style={{
                        top: (target.start - gridStart) * pixelsPerMinute,
                        height: (target.end - target.start) * pixelsPerMinute,
                    }}
                />
            )}
            {placed.map((span) => (
                <GridBlock
                    key={span.item.id}
                    placed={span}
                    gridStart={gridStart}
                    pixelsPerMinute={pixelsPerMinute}
                    draggable={canDrag && span.item.actions.includes('move')}
                    onOpen={() => onOpen(span.item)}
                    onDrag={(offset) => onDrag(span.item, offset)}
                    onDrop={(offset) => onDrop(span.item, offset)}
                />
            ))}
        </div>
    );
}
