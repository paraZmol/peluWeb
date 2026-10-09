import { Clock, MapPin, User } from 'lucide-react';
import type { ReactNode } from 'react';
import { formatPrice, formatTime } from '@/lib/format';
import { DateTile } from './date-tile';
import { PickedTime } from './picked-time';

const longDateFormat = new Intl.DateTimeFormat('es-PE', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

/** "2026-10-14" → "miércoles, 14 de octubre", without timezone shifts. */
function formatLongDate(date: string): string {
    const [year, month, day] = date.split('-').map(Number);

    return longDateFormat.format(new Date(year, month - 1, day));
}

type Props = {
    date: string;
    time: string;
    endTime?: string;
    staffName: string;
    /** The team reads "Atiende"; the client, "Te atiende". */
    staffLabel?: string;
    duration?: string;
    address?: string | null;
    items: { name: string; price?: string }[];
    total: string | null;
    /** e.g. "desde" when the price depends on who attends. */
    totalPrefix?: string;
    footer?: ReactNode;
    /** The time was tapped a moment ago: let it land with a short motion. */
    timeJustPicked?: boolean;
};

/** When, with whom, what and how much: shown before and after booking. */
export function BookingSummary({
    date,
    time,
    endTime,
    staffName,
    staffLabel = 'Te atiende',
    duration,
    address,
    items,
    total,
    totalPrefix,
    footer,
    timeJustPicked = false,
}: Props) {
    const row = (icon: ReactNode, label: string, value: ReactNode) => (
        <div className="flex items-start gap-3">
            <span
                className="mt-0.5 flex w-13 shrink-0 justify-center text-brand-text [&_svg]:size-5"
                aria-hidden
            >
                {icon}
            </span>
            <div className="min-w-0">
                <dt className="text-xs text-muted-foreground">{label}</dt>
                <dd className="break-words">{value}</dd>
            </div>
        </div>
    );

    return (
        <dl className="overflow-hidden rounded-xl skin-raised">
            <div className="flex items-center gap-3.5 border-b p-4 sm:px-5">
                <DateTile date={date} />
                <div className="min-w-0">
                    <dt className="sr-only">Día y hora</dt>
                    <dd>
                        <span className="block text-sm text-muted-foreground first-letter:uppercase">
                            {formatLongDate(date)}
                        </span>
                        <span className="block text-xl font-extrabold tabular-nums">
                            {timeJustPicked ? (
                                <PickedTime>{formatTime(time)}</PickedTime>
                            ) : (
                                formatTime(time)
                            )}
                            {endTime && ` – ${formatTime(endTime)}`}
                        </span>
                    </dd>
                </div>
            </div>

            <div className="flex flex-col gap-3 p-4 sm:px-5">
                {row(<User />, staffLabel, staffName)}
                {duration && row(<Clock />, 'Duración', duration)}
                {address && row(<MapPin />, 'Dirección', address)}
            </div>

            <div className="flex flex-col gap-3 border-t p-4 sm:px-5">
                <ul className="flex flex-col gap-1.5">
                    {items.map((item) => (
                        <li
                            key={item.name}
                            className="flex justify-between gap-3"
                        >
                            <span className="min-w-0 break-words">
                                {item.name}
                            </span>
                            {item.price && (
                                <span className="shrink-0 text-muted-foreground tabular-nums">
                                    {formatPrice(item.price)}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>

                {total !== null && (
                    <div className="flex items-baseline justify-between border-t border-dashed pt-3">
                        <dt className="font-medium">Total</dt>
                        <dd className="text-xl font-extrabold tabular-nums">
                            {totalPrefix && (
                                <span className="text-sm font-normal text-muted-foreground">
                                    {totalPrefix}{' '}
                                </span>
                            )}
                            {formatPrice(total)}
                        </dd>
                    </div>
                )}
                {footer}
            </div>
        </dl>
    );
}
