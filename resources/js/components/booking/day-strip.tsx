import { useEffect, useRef } from 'react';
import type { KeyboardEvent } from 'react';
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
    /** Read by screen readers: "Día de la reserva" for clients, "Día de la cita" for the team. */
    label?: string;
};

/** Horizontal strip of days; closed days stay visible but cannot be picked. */
export function DayStrip({
    days,
    value,
    onChange,
    label = 'Día de la reserva',
}: Props) {
    const selected = useRef<HTMLButtonElement>(null);
    // Set by the arrow keys, so focus follows the day they moved to.
    const followFocus = useRef(false);
    const open = days.filter((day) => !day.closed);
    const tabStop = value ?? open[0]?.date;

    /** Like a radio group: arrows walk the open days, one tab stop in all. */
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
        const next =
            step === undefined
                ? undefined
                : open[open.findIndex((day) => day.date === value) + step];

        if (next) {
            event.preventDefault();
            followFocus.current = true;
            onChange(next.date);
        }
    };

    useEffect(() => {
        selected.current?.scrollIntoView({
            block: 'nearest',
            inline: 'center',
        });

        if (followFocus.current) {
            followFocus.current = false;
            selected.current?.focus();
        }
    }, [value]);

    return (
        <div
            role="radiogroup"
            aria-label={label}
            onKeyDown={onKeyDown}
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
                        tabIndex={day.date === tabStop ? 0 : -1}
                        aria-label={`${fullFormat.format(date)}${day.closed ? ', cerrado' : ''}`}
                        disabled={day.closed}
                        onClick={() => onChange(day.date)}
                        className={cn(
                            'flex h-20 w-16 shrink-0 snap-start flex-col items-center justify-center rounded-2xl border text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            isSelected
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'bg-card hover:border-foreground/20 hover:bg-accent',
                            day.closed && 'opacity-40',
                        )}
                    >
                        <span
                            className={cn(
                                'text-xs capitalize',
                                !isSelected && 'text-muted-foreground',
                            )}
                        >
                            {index === 0
                                ? 'Hoy'
                                : weekdayFormat.format(date).replace('.', '')}
                        </span>
                        <span className="text-xl leading-7 font-semibold tabular-nums">
                            {date.getDate()}
                        </span>
                        <span
                            className={cn(
                                'text-xs',
                                !isSelected && 'text-muted-foreground',
                            )}
                        >
                            {monthFormat.format(date).replace('.', '')}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
