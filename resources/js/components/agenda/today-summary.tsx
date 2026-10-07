import { Clock, Coffee, Scissors } from 'lucide-react';
import type { ReactNode } from 'react';
import type { AgendaBooking } from '@/components/booking/types';
import { useNow } from '@/hooks/use-now';
import { formatMinutes, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = { bookings: AgendaBooking[]; showStaff: boolean };

const REFRESH_MS = 30_000;
const MS_PER_MINUTE = 60_000;

/** "en 25 min", "en 1 h 10 min", or "ahora" once the time has come. */
function countdown(startsAt: string, now: number): string {
    const minutes = Math.ceil((Date.parse(startsAt) - now) / MS_PER_MINUTE);

    return minutes <= 0 ? 'ahora' : `en ${formatMinutes(minutes)}`;
}

/** Today at a glance: who is in the chair and who comes next. */
export function TodaySummary({ bookings, showStaff }: Props) {
    const now = useNow(REFRESH_MS);

    if (now === null) {
        return null;
    }

    const current = bookings.filter(
        (booking) => booking.status === 'in_service',
    );
    const next = bookings.find(
        (booking) =>
            (booking.status === 'confirmed' || booking.status === 'pending') &&
            Date.parse(booking.endsAt) > now,
    );

    const row = (
        key: string,
        icon: ReactNode,
        label: string,
        booking: AgendaBooking,
        detail: string,
    ) => (
        <li key={key} className="flex items-center gap-3">
            <span
                aria-hidden
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-full [&_svg]:size-5',
                    booking.status === 'in_service'
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-background text-primary',
                )}
            >
                {icon}
            </span>
            <p className="min-w-0 flex-1">
                <span className="block text-xs text-muted-foreground tabular-nums">
                    {label}
                </span>
                <span className="block truncate font-medium">
                    {booking.client.name}
                </span>
                {showStaff && (
                    <span className="block truncate text-sm text-muted-foreground">
                        Con {booking.staffName}
                    </span>
                )}
            </p>
            <p className="shrink-0 text-right text-sm font-medium whitespace-nowrap tabular-nums">
                {detail}
            </p>
        </li>
    );

    if (current.length === 0 && !next) {
        return (
            <p className="flex items-center gap-3 rounded-xl bg-muted/60 px-4 py-3 text-sm text-muted-foreground">
                <Coffee className="size-5 shrink-0" aria-hidden />
                {bookings.length === 0
                    ? 'Hoy no tienes citas.'
                    : 'No quedan más citas por hoy.'}
            </p>
        );
    }

    return (
        <ul
            aria-label="Resumen de hoy"
            className="flex flex-col gap-3 rounded-xl bg-muted/60 p-4"
        >
            {current.map((booking) =>
                row(
                    `current-${booking.id}`,
                    <Scissors />,
                    'En atención',
                    booking,
                    `hasta ${formatTime(booking.endTime)}`,
                ),
            )}
            {next &&
                row(
                    `next-${next.id}`,
                    <Clock />,
                    `Sigue · ${formatTime(next.time)}`,
                    next,
                    countdown(next.startsAt, now),
                )}
        </ul>
    );
}
