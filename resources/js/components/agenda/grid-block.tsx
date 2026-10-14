import { motion, useReducedMotion } from 'motion/react';
import type { PanInfo } from 'motion/react';
import { useRef } from 'react';
import type { AgendaBooking } from '@/components/booking/types';
import { servicesLine } from '@/lib/companion';
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
const MERIDIEM_LENGTH = ' a. m.'.length;
/** Below these heights a line is dropped instead of being cut in half. */
const TWO_LINES_PX = 32;
const THREE_LINES_PX = 56;

/** The left edge carries the status, so it reads even on a one-line block. */
const TONE: Record<string, string> = {
    pending: 'border-dashed border-warning/60 border-l-warning bg-warning/15',
    confirmed: 'border-primary/30 border-l-primary bg-primary/10',
    in_service: 'border-primary bg-primary text-primary-foreground',
    completed:
        'border-border border-l-muted-foreground/40 bg-muted text-muted-foreground',
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
    const reduceMotion = useReducedMotion();
    const { item: booking, start, end, lane, lanes } = placed;
    const width = 100 / lanes;
    const height = Math.max((end - start) * pixelsPerMinute - 2, MIN_HEIGHT_PX);
    const time = formatTime(booking.time);
    // The browser fires a click when a drag ends: that is not "open it".
    const wasDragged = useRef(false);

    return (
        <motion.button
            type="button"
            layout={!reduceMotion}
            drag={draggable}
            dragSnapToOrigin
            dragMomentum={false}
            dragElastic={0}
            whileDrag={{ scale: 1.03, opacity: 0.9, zIndex: 30 }}
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
            aria-label={`${booking.client.name}, ${time}, ${booking.statusLabel}`}
            style={{
                top: (start - gridStart) * pixelsPerMinute,
                height,
                left: `calc(${lane * width}% + 2px)`,
                width: `calc(${width}% - 4px)`,
            }}
            className={cn(
                'absolute flex flex-col justify-start overflow-hidden rounded-[6px] border border-l-[3px] px-1.5 text-left text-xs leading-[15px] shadow-xs transition-shadow hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                height < THREE_LINES_PX ? 'py-px' : 'py-1',
                height < TWO_LINES_PX && 'justify-center',
                draggable &&
                    'cursor-grab active:cursor-grabbing active:shadow-lg',
                TONE[booking.status] ?? TONE.completed,
            )}
        >
            {height < TWO_LINES_PX ? (
                <span className="block truncate">
                    <span className="font-semibold tabular-nums">
                        {time.slice(0, -MERIDIEM_LENGTH)}
                    </span>{' '}
                    {booking.client.name}
                </span>
            ) : (
                <>
                    <span className="block truncate font-semibold tabular-nums">
                        {time}
                    </span>
                    <span className="block truncate">
                        {booking.client.name}
                    </span>
                </>
            )}
            {height >= THREE_LINES_PX && (
                <span className="block truncate opacity-80">
                    {servicesLine(booking.services)}
                </span>
            )}
        </motion.button>
    );
}
