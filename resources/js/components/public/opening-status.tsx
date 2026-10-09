import { formatTime } from '@/lib/format';
import { weekdayLabel } from '@/lib/schedule';
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

const TONES = {
    /** Over a photo. */
    glass: 'skin-glass rounded-full px-3 py-1.5',
    /** A tilted sticker on a color block. */
    sticker: 'skin-raised -rotate-2 rounded-full px-3 py-1.5',
    plain: '',
};

type Props = {
    hours: PublicOpeningHours;
    tone?: keyof typeof TONES;
    className?: string;
};

/** "Open now" line: the first thing visitors look for, shown once, in the cover. */
export function OpeningStatus({ hours, tone = 'plain', className }: Props) {
    return (
        <p
            className={cn(
                'flex w-fit max-w-full items-center gap-2 text-sm font-semibold',
                TONES[tone],
                className,
            )}
        >
            <span
                aria-hidden
                className={cn(
                    'size-2.5 shrink-0 rounded-full',
                    hours.status.isOpen
                        ? 'bg-success ring-3 ring-success/25'
                        : 'bg-muted-foreground',
                )}
            />
            <span className="min-w-0">{statusText(hours)}</span>
        </p>
    );
}
