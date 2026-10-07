import { CalendarDays, Clock, MapPin, User } from 'lucide-react';
import type { ReactNode } from 'react';
import { formatDate, formatPrice, formatTime } from '@/lib/format';

type Props = {
    date: string;
    time: string;
    endTime?: string;
    staffName: string;
    duration?: string;
    address?: string | null;
    items: { name: string; price?: string }[];
    total: string | null;
    /** e.g. "desde" when the price depends on who attends. */
    totalPrefix?: string;
    footer?: ReactNode;
};

/** When, with whom, what and how much: shown before and after booking. */
export function BookingSummary({
    date,
    time,
    endTime,
    staffName,
    duration,
    address,
    items,
    total,
    totalPrefix,
    footer,
}: Props) {
    const row = (icon: ReactNode, label: string, value: ReactNode) => (
        <div className="flex items-start gap-3">
            <span className="mt-0.5 shrink-0 text-primary" aria-hidden>
                {icon}
            </span>
            <dt className="sr-only">{label}</dt>
            <dd className="min-w-0 break-words">{value}</dd>
        </div>
    );

    return (
        <dl className="flex flex-col gap-3 rounded-xl border bg-card p-5">
            {row(
                <CalendarDays className="size-5" />,
                'Día y hora',
                <>
                    <span className="block font-medium capitalize">
                        {formatDate(date)}
                    </span>
                    <span className="block text-lg font-semibold tabular-nums">
                        {formatTime(time)}
                        {endTime && ` – ${formatTime(endTime)}`}
                    </span>
                </>,
            )}
            {row(<User className="size-5" />, 'Te atiende', staffName)}
            {duration &&
                row(<Clock className="size-5" />, 'Duración', duration)}
            {address &&
                row(<MapPin className="size-5" />, 'Dirección', address)}

            <ul className="flex flex-col gap-1 border-t pt-3">
                {items.map((item) => (
                    <li key={item.name} className="flex justify-between gap-3">
                        <span className="min-w-0 break-words">{item.name}</span>
                        {item.price && (
                            <span className="shrink-0 tabular-nums">
                                {formatPrice(item.price)}
                            </span>
                        )}
                    </li>
                ))}
            </ul>

            {total !== null && (
                <div className="flex items-baseline justify-between border-t pt-3">
                    <dt className="font-medium">Total</dt>
                    <dd className="text-lg font-semibold tabular-nums">
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
        </dl>
    );
}
