import type { AgendaBooking } from '@/components/booking/types';
import { formatCents, formatPrice, sumAmounts } from '@/lib/format';
import { toCents } from '@/lib/money';

type Props = {
    booking: AgendaBooking;
    discountCents: number;
    totalCents: number;
};

/** How the amount to collect comes about, line by line, and the total. */
export function CheckoutSummary({ booking, discountCents, totalCents }: Props) {
    const { promotion } = booking;
    const lines = [
        {
            // Not typed here: the booking already carries it in its price.
            label: `Promoción ${promotion?.name ?? ''}`,
            amount: `− ${formatPrice(promotion?.saved ?? '0')}`,
            isShown: promotion !== null,
        },
        {
            label: 'Seña ya pagada',
            amount: `− ${formatPrice(booking.depositPaid)}`,
            isShown: toCents(booking.depositPaid) !== 0,
        },
        {
            label: promotion ? 'Descuento manual' : 'Descuento',
            amount: `− ${formatCents(discountCents)}`,
            isShown: discountCents > 0,
        },
    ];

    return (
        <div className="rounded-xl border bg-card">
            <dl className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1.5 p-4 text-sm">
                <dt className="break-words">{booking.services.join(', ')}</dt>
                <dd className="text-right whitespace-nowrap tabular-nums">
                    {formatPrice(
                        promotion
                            ? sumAmounts([booking.price, promotion.saved])
                            : booking.price,
                    )}
                </dd>
                {lines
                    .filter((line) => line.isShown)
                    .map((line) => (
                        <div key={line.label} className="contents">
                            <dt className="text-muted-foreground">
                                {line.label}
                            </dt>
                            <dd className="text-right whitespace-nowrap text-muted-foreground tabular-nums">
                                {line.amount}
                            </dd>
                        </div>
                    ))}
            </dl>
            <p className="flex items-baseline justify-between gap-4 border-t p-4">
                <span className="text-sm font-medium">Total a cobrar</span>
                <output
                    aria-live="polite"
                    className="text-3xl font-semibold whitespace-nowrap tabular-nums"
                >
                    {formatCents(Math.max(0, totalCents))}
                </output>
            </p>
        </div>
    );
}
