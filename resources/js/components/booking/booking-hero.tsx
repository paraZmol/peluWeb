import { FitTitle } from '@/components/public/fit-title';
import { SalonMark } from '@/components/public/salon-mark';
import { useSalonTheme } from '@/components/salon/brand-theme';
import { cn } from '@/lib/utils';
import { StatusBadge } from './status-badge';
import { SuccessCheck } from './success-check';
import type { ClientBooking } from './types';

type Props = { booking: ClientBooking; className?: string };

/**
 * Short cover of a booking, in its salon's template. The payload brings no
 * cover photo, so the photo covers use their no-photo look (a soft block of
 * the brand color); Vibrante uses its solid block of color.
 */
export function BookingHero({ booking, className }: Props) {
    const { layout } = useSalonTheme();
    const { salon } = booking;
    const isBlock = layout.hero === 'color-block';
    const celebrates = booking.status === 'confirmed' && booking.isUpcoming;

    return (
        <header
            className={cn(
                'relative flex min-h-55 flex-col justify-end gap-3 overflow-hidden rounded-3xl p-5 sm:p-8',
                isBlock
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-linear-to-br from-brand-soft to-band shadow-card',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-4">
                <SalonMark name={salon.name} logoUrl={null} size="lg" />
                {celebrates && <SuccessCheck celebrateKey={booking.code} />}
            </div>
            <p role="status" className="sr-only">
                {`Tu reserva en ${salon.name}: ${booking.statusLabel}.`}
            </p>
            <div className="@container">
                <FitTitle text={salon.name} size="title-2" />
            </div>
            <StatusBadge
                status={booking.status}
                label={booking.statusLabel}
                className={cn(
                    'px-3 py-1 text-sm',
                    isBlock && 'bg-primary-foreground text-primary',
                )}
            />
        </header>
    );
}
