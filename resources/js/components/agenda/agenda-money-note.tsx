import type { AgendaBooking } from '@/components/booking/types';
import { formatPrice } from '@/lib/format';
import { toCents } from '@/lib/money';

type Props = { booking: AgendaBooking };

/**
 * What happened with the money of a visit that is over: what was charged
 * when it was completed, or where the deposit of a cancelled one stands.
 */
export function AgendaMoneyNote({ booking }: Props) {
    const { charged, deposit, status } = booking;

    if (status === 'completed' && charged) {
        const extras = [
            { label: 'descuento', amount: charged.discount },
            { label: 'propina', amount: charged.tip },
        ].filter((extra) => toCents(extra.amount) !== 0);

        return (
            <p className="rounded-lg bg-muted p-3 text-sm">
                Cobrado{' '}
                <strong className="tabular-nums">
                    {formatPrice(charged.total)}
                </strong>
                {extras.map((extra) => (
                    <span key={extra.label} className="tabular-nums">
                        {' · '}
                        {extra.label} {formatPrice(extra.amount)}
                    </span>
                ))}
            </p>
        );
    }

    if (status !== 'cancelled' || !deposit?.isPaid) {
        return null;
    }

    const amount = (
        <strong className="tabular-nums">{formatPrice(deposit.amount)}</strong>
    );

    if (deposit.isRefunded) {
        return (
            <p className="rounded-lg bg-muted p-3 text-sm">
                Seña de {amount} devuelta.
            </p>
        );
    }

    return booking.actions.includes('refundDeposit') ? (
        <p className="rounded-lg bg-warning/10 p-3 text-sm">
            Seña de {amount} pendiente de devolver.
        </p>
    ) : (
        <p className="rounded-lg bg-muted p-3 text-sm">
            Seña de {amount} recibida.
        </p>
    );
}
