import { Star } from 'lucide-react';
import { PromotionLine } from '@/components/booking/promotion-line';
import { StatusBadge } from '@/components/booking/status-badge';
import type {
    AgendaBooking,
    BookingDay,
    StaffOption,
} from '@/components/booking/types';
import { formatPrice, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { AgendaActions } from './agenda-actions';
import { AgendaMoneyNote } from './agenda-money-note';

type Props = {
    booking: AgendaBooking;
    showStaff: boolean;
    days: BookingDay[];
    staffOptions: StaffOption[];
    className?: string;
};

const DIMMED = ['cancelled', 'no_show', 'completed'];

export function AgendaItem({
    booking,
    showStaff,
    days,
    staffOptions,
    className,
}: Props) {
    return (
        <article
            aria-label={`${booking.client.name}, ${formatTime(booking.time)}`}
            className={cn(
                'flex flex-col gap-3 rounded-xl border bg-card p-4',
                booking.status === 'in_service' && 'border-primary',
                // A deposit still owed is work to do: it stays at full strength.
                DIMMED.includes(booking.status) &&
                    !booking.actions.includes('refundDeposit') &&
                    'opacity-60',
                className,
            )}
        >
            {/* Phone: time and status on top, details below. Wider: one row. */}
            <div className="grid grid-cols-[1fr_auto] items-start gap-x-4 gap-y-2 sm:grid-cols-[7.5rem_1fr_auto]">
                <p className="font-semibold whitespace-nowrap tabular-nums">
                    {formatTime(booking.time)}
                    <span className="block text-sm font-normal text-muted-foreground">
                        hasta {formatTime(booking.endTime)}
                    </span>
                </p>
                <StatusBadge
                    status={booking.status}
                    label={booking.statusLabel}
                    className="mt-0.5 sm:col-start-3"
                />
                <div className="col-span-2 min-w-0 sm:col-span-1 sm:col-start-2 sm:row-start-1">
                    <p className="flex flex-wrap items-center gap-2 text-base font-medium break-words">
                        {booking.client.name}
                        {booking.isVip && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-primary/15 px-2 py-0.5 text-xs text-primary">
                                <Star className="size-3" aria-hidden /> VIP
                            </span>
                        )}
                    </p>
                    <p className="text-sm break-words text-muted-foreground">
                        {booking.services.join(', ')} ·{' '}
                        <span className="whitespace-nowrap tabular-nums">
                            {formatPrice(booking.price)}
                        </span>
                    </p>
                    {booking.promotion && (
                        <PromotionLine
                            name={booking.promotion.name}
                            amount={booking.promotion.saved}
                            className="mt-1"
                        />
                    )}
                    {showStaff && (
                        <p className="text-sm text-muted-foreground">
                            Con {booking.staffName}
                        </p>
                    )}
                    {booking.note && (
                        <p className="mt-2 border-l-2 border-primary/40 pl-3 text-sm break-words italic">
                            "{booking.note}"
                        </p>
                    )}
                    {booking.referencePhotoUrl && (
                        <a
                            href={booking.referencePhotoUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-2 inline-flex items-center gap-2 rounded-lg border p-1 pr-3 text-sm hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <img
                                src={booking.referencePhotoUrl}
                                alt=""
                                className="size-12 rounded-md object-cover"
                                loading="lazy"
                            />
                            Ver foto de referencia
                        </a>
                    )}
                    {booking.client.noShows > 0 && (
                        <p className="mt-1 text-xs text-warning">
                            Faltó {booking.client.noShows}{' '}
                            {booking.client.noShows === 1 ? 'vez' : 'veces'}{' '}
                            antes
                        </p>
                    )}
                </div>
            </div>

            {booking.deposit && booking.status === 'pending' && (
                <p className="rounded-lg bg-warning/10 p-3 text-sm">
                    Espera la seña de{' '}
                    <strong className="tabular-nums">
                        {formatPrice(booking.deposit.amount)}
                    </strong>
                    . Confírmala cuando recibas el comprobante.
                </p>
            )}
            <AgendaMoneyNote booking={booking} />

            <AgendaActions
                booking={booking}
                days={days}
                staffOptions={staffOptions}
            />
        </article>
    );
}
