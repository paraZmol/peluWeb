import { Clock, MapPin, User } from 'lucide-react';
import type { ReactNode } from 'react';
import { formatTime } from '@/lib/format';
import { DateTile } from './date-tile';
import { PickedTime } from './picked-time';
import { SummaryMoney } from './summary-money';
import type { SummaryItem } from './summary-money';

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
    items: SummaryItem[];
    total: string | null;
    /** e.g. "desde" when the price depends on who attends. */
    totalPrefix?: string;
    /** The total without the promotion, shown crossed out. */
    listTotal?: string | null;
    /** The promotion applied and what it takes off. */
    promotion?: { name: string; amount: string } | null;
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
    listTotal,
    promotion,
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
                <SummaryMoney
                    items={items}
                    total={total}
                    totalPrefix={totalPrefix}
                    listTotal={listTotal}
                    promotion={promotion}
                />
                {footer}
            </div>
        </dl>
    );
}
