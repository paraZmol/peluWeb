import { motion } from 'motion/react';
import type { PanInfo } from 'motion/react';
import { useRef } from 'react';
import type { AgendaBooking } from '@/components/booking/types';
import { formatTime } from '@/lib/format';
import type { Placed } from '@/lib/time-grid';
import { cn } from '@/lib/utils';

type Props = {
    placed: Placed<AgendaBooking>;
    /** Top of the grid in minutes since midnight, and its scale. */
    gridStart: number;
    pixelsPerMinute: number;
    draggable: boolean;
    onOpen: () => void;
    onDrag: (offset: PanInfo['offset']) => void;
    onDrop: (offset: PanInfo['offset']) => void;
};

const MIN_HEIGHT_PX = 22;

const TONE: Record<string, string> = {
    pending: 'border-warning/50 bg-warning/15',
    confirmed: 'border-primary/40 bg-primary/15',
    in_service: 'border-primary bg-primary text-primary-foreground',
    completed: 'border-border bg-muted text-muted-foreground',
};

/** One visit in the time grid: tap to open it, drag to move it. */
export function GridBlock({
    placed,
    gridStart,
    pixelsPerMinute,
    draggable,
    onOpen,
    onDrag,
    onDrop,
}: Props) {
    const { item: booking, start, end, lane, lanes } = placed;
    const width = 100 / lanes;
    // The browser fires a click when a drag ends: that is not "open it".
    const wasDragged = useRef(false);

    return (
        <motion.button
            type="button"
            layout
            drag={draggable}
            dragSnapToOrigin
            dragMomentum={false}
            dragElastic={0}
            whileDrag={{ scale: 1.03, zIndex: 30 }}
            onDragStart={() => {
                wasDragged.current = true;
            }}
            onDrag={(_, info) => onDrag(info.offset)}
            onDragEnd={(_, info) => onDrop(info.offset)}
            onClick={() => {
                if (!wasDragged.current) {
                    onOpen();
                }

                wasDragged.current = false;
            }}
            aria-label={`${booking.client.name}, ${formatTime(booking.time)}, ${booking.statusLabel}`}
            style={{
                top: (start - gridStart) * pixelsPerMinute,
                height: Math.max(
                    (end - start) * pixelsPerMinute - 2,
                    MIN_HEIGHT_PX,
                ),
                left: `calc(${lane * width}% + 2px)`,
                width: `calc(${width}% - 4px)`,
            }}
            className={cn(
                'absolute overflow-hidden rounded-md border px-1.5 py-0.5 text-left text-xs leading-tight shadow-xs transition-shadow focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                draggable &&
                    'cursor-grab active:cursor-grabbing active:shadow-lg',
                TONE[booking.status] ?? TONE.completed,
            )}
        >
            <span className="block font-medium tabular-nums">
                {formatTime(booking.time)}
            </span>
            <span className="block truncate">{booking.client.name}</span>
            <span className="block truncate opacity-80">
                {booking.services.join(', ')}
            </span>
        </motion.button>
    );
}
