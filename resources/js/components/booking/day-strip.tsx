import { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';
import type { BookingDay } from './types';

const weekdayFormat = new Intl.DateTimeFormat('es-PE', { weekday: 'short' });
const monthFormat = new Intl.DateTimeFormat('es-PE', { month: 'short' });
const fullFormat = new Intl.DateTimeFormat('es-PE', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

/** Built from parts so the browser's timezone never shifts the day. */
function toDate(date: string): Date {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day);
}

type Props = {
    days: BookingDay[];
    value: string | null;
    onChange: (date: string) => void;
};

/** Horizontal strip of days; closed days stay visible but cannot be picked. */
export function DayStrip({ days, value, onChange }: Props) {
    const selected = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        selected.current?.scrollIntoView({
            block: 'nearest',
            inline: 'center',
        });
    }, [value]);

    return (
        <div
            role="radiogroup"
            aria-label="Día de la cita"
            className="-mx-4 flex snap-x gap-2 overflow-x-auto px-4 pb-2"
        >
            {days.map((day, index) => {
                const date = toDate(day.date);
                const isSelected = day.date === value;

                return (
                    <button
                        key={day.date}
                        ref={isSelected ? selected : undefined}
                        type="button"
                        role="radio"
                        aria-checked={isSelected}
                        aria-label={`${fullFormat.format(date)}${day.closed ? ', cerrado' : ''}`}
                        disabled={day.closed}
                        onClick={() => onChange(day.date)}
                        className={cn(
                            'flex h-20 w-16 shrink-0 snap-start flex-col items-center justify-center rounded-2xl border text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            isSelected
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'bg-card hover:bg-accent',
                            day.closed && 'opacity-40',
                        )}
                    >
                        <span className="text-xs capitalize">
                            {index === 0
                                ? 'Hoy'
                                : weekdayFormat.format(date).replace('.', '')}
                        </span>
                        <span className="text-xl font-semibold tabular-nums">
                            {date.getDate()}
                        </span>
                        <span className="text-xs">
                            {monthFormat.format(date).replace('.', '')}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
