import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { SalonMark } from '@/components/public/salon-mark';
import { BrandTheme } from '@/components/salon/brand-theme';
import { formatDate, formatPrice, formatTime } from '@/lib/format';
import { show } from '@/routes/bookings';
import { StatusBadge } from './status-badge';
import type { ClientBooking } from './types';

/**
 * One booking of "Mis reservas". The list mixes salons, so each card wears
 * its own salon's template and color (ui-system §7).
 */
export function ClientBookingCard({ booking }: { booking: ClientBooking }) {
    const { salon } = booking;

    return (
        <BrandTheme brand={salon.brand} theme={salon.theme} className="h-full">
            <Link
                href={show(booking.code)}
                className="flex h-full items-start gap-4 rounded-xl p-4 skin-card transition-transform duration-150 outline-none hover:-translate-y-0.5 focus-visible:ring-[3px] focus-visible:ring-ring/60 sm:p-5"
            >
                {/* The payload has no logo yet: the salon's initials on its color. */}
                <SalonMark name={salon.name} logoUrl={null} />
                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <div className="flex items-start justify-between gap-3">
                        <p className="min-w-0 display-type text-xl break-words">
                            {salon.name}
                        </p>
                        <StatusBadge
                            status={booking.status}
                            label={booking.statusLabel}
                            className="mt-0.5 shrink-0"
                        />
                    </div>
                    <p className="text-lg leading-tight font-extrabold tabular-nums first-letter:uppercase">
                        {formatDate(booking.date)} · {formatTime(booking.time)}
                    </p>
                    <p className="text-sm break-words text-muted-foreground">
                        Con {booking.staffName}
                    </p>
                    <p className="flex items-baseline justify-between gap-3 text-sm">
                        <span className="min-w-0 truncate text-muted-foreground">
                            {booking.services
                                .map((service) => service.name)
                                .join(', ')}
                        </span>
                        <span className="shrink-0 font-bold tabular-nums">
                            {formatPrice(booking.price)}
                        </span>
                    </p>
                </div>
                <ChevronRight
                    className="mt-1 size-5 shrink-0 text-brand-text"
                    aria-hidden
                />
            </Link>
        </BrandTheme>
    );
}
