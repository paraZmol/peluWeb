import type { DropTarget } from '@/hooks/use-grid-drag';
import { formatTime } from '@/lib/format';
import { toTime } from '@/lib/time-grid';
import { cn } from '@/lib/utils';

type Props = {
    /** Whole hours to label (0–23). */
    hours: number[];
    /** Minutes since midnight → pixels from the top of the grid. */
    top: (minutes: number) => number;
    /** Where the visit being dragged would start, to read its exact time. */
    target: DropTarget | null;
    className?: string;
};

const MERIDIEM_LENGTH = ' a. m.'.length;

/** The hours down the side of the time grid. */
export function TimeGridHours({ hours, top, target, className }: Props) {
    return (
        <div className={className} aria-hidden>
            {hours.map((hour) => (
                <span
                    key={hour}
                    className="absolute right-2 -translate-y-1/2 text-xs whitespace-nowrap text-muted-foreground tabular-nums first:translate-y-1"
                    style={{ top: top(hour * 60) }}
                >
                    {/* "9 a. m.": the minutes only add noise on the hour. */}
                    {formatTime(toTime(hour * 60)).replace(':00', '')}
                </span>
            ))}
            {target && (
                <span
                    className={cn(
                        'absolute inset-x-1 -translate-y-1/2 rounded-sm py-0.5 text-center text-xs font-semibold whitespace-nowrap tabular-nums',
                        target.conflict
                            ? 'bg-card text-destructive-foreground ring-1 ring-destructive'
                            : 'bg-primary text-primary-foreground',
                    )}
                    style={{ top: top(target.start) }}
                >
                    {formatTime(toTime(target.start)).slice(
                        0,
                        -MERIDIEM_LENGTH,
                    )}
                </span>
            )}
        </div>
    );
}
