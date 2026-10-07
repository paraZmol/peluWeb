import { useState } from 'react';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { TimeWheel } from './time-wheel';

type Props = {
    start: string;
    end: string;
    onChange: (start: string, end: string) => void;
    startLabel?: string;
    endLabel?: string;
};

/**
 * Two times edited with one alarm-style wheel: tap "Entrada" or "Salida"
 * and spin. One wheel at a time fits a 360 px screen without scrolling.
 */
export function TimeRangePicker({
    start,
    end,
    onChange,
    startLabel = 'Entrada',
    endLabel = 'Salida',
}: Props) {
    const [editing, setEditing] = useState<'start' | 'end'>('start');
    const fields = [
        { key: 'start', label: startLabel, value: start },
        { key: 'end', label: endLabel, value: end },
    ] as const;

    return (
        <div className="flex flex-col gap-3">
            <div className="grid grid-cols-2 gap-2">
                {fields.map((field) => (
                    <button
                        key={field.key}
                        type="button"
                        aria-pressed={editing === field.key}
                        onClick={() => setEditing(field.key)}
                        className={cn(
                            'flex min-h-14 flex-col items-center justify-center rounded-xl border bg-card px-2 py-2 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            editing === field.key
                                ? 'border-primary bg-primary/10'
                                : 'hover:bg-accent',
                        )}
                    >
                        <span className="text-xs text-muted-foreground">
                            {field.label}
                        </span>
                        <span className="text-lg font-semibold tabular-nums">
                            {formatTime(field.value)}
                        </span>
                    </button>
                ))}
            </div>

            <TimeWheel
                key={editing}
                label={editing === 'start' ? startLabel : endLabel}
                value={editing === 'start' ? start : end}
                onChange={(value) =>
                    editing === 'start'
                        ? onChange(value, end)
                        : onChange(start, value)
                }
            />
        </div>
    );
}
