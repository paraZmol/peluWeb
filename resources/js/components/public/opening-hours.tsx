import { Clock } from 'lucide-react';
import { formatTime } from '@/lib/format';
import { WEEKDAYS, weekdayLabel } from '@/lib/schedule';
import { cn } from '@/lib/utils';
import type { PublicOpeningHours } from './types';

function statusText({ status, closedToday }: PublicOpeningHours): string {
    if (status.isOpen && status.time) {
        return `Abierto ahora · cierra a las ${formatTime(status.time)}`;
    }

    const prefix = closedToday ? `Hoy cerrado por ${closedToday}` : 'Cerrado';

    if (!status.time || status.daysAhead === null) {
        return prefix;
    }

    const day =
        status.daysAhead === 0
            ? 'hoy'
            : status.daysAhead === 1
              ? 'mañana'
              : `el ${weekdayLabel(status.weekday ?? 0).toLowerCase()}`;

    return `${prefix} · abre ${day} a las ${formatTime(status.time)}`;
}

/** "Open now" line. Shown near the top: it is what visitors look for first. */
export function OpeningStatus({ hours }: { hours: PublicOpeningHours }) {
    return (
        <p className="flex items-center gap-2 text-sm font-medium">
            <span
                aria-hidden
                className={cn(
                    'size-2.5 shrink-0 rounded-full',
                    hours.status.isOpen ? 'bg-success' : 'bg-muted-foreground',
                )}
            />
            {statusText(hours)}
        </p>
    );
}

export function OpeningHours({ hours }: { hours: PublicOpeningHours }) {
    return (
        <div className="flex flex-col gap-4">
            <h2 className="flex items-center gap-2 font-display text-2xl font-semibold">
                <Clock className="size-5 text-primary" aria-hidden />
                Horario de atención
            </h2>
            <dl className="divide-y rounded-xl border bg-card">
                {hours.days.map((day) => (
                    <div
                        key={day.weekday}
                        className={cn(
                            'flex items-start justify-between gap-4 px-4 py-3 text-sm',
                            day.weekday === hours.today && 'font-semibold',
                        )}
                    >
                        <dt>
                            {WEEKDAYS[day.weekday - 1].label}
                            {day.weekday === hours.today && (
                                <span className="sr-only"> (hoy)</span>
                            )}
                        </dt>
                        <dd className="text-right tabular-nums">
                            {day.ranges.length === 0 ? (
                                <span className="font-normal text-muted-foreground">
                                    Cerrado
                                </span>
                            ) : (
                                day.ranges.map((range) => (
                                    <span key={range.start} className="block">
                                        {formatTime(range.start)} –{' '}
                                        {formatTime(range.end)}
                                    </span>
                                ))
                            )}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
