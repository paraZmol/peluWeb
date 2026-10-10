import { Check, Undo2 } from 'lucide-react';
import type { AgendaBooking } from '@/components/booking/types';
import { Button } from '@/components/ui/button';
import { confirm, refundDeposit } from '@/routes/agenda/bookings';
import { PaymentMethodDialog } from './payment-method-dialog';

type Props = { booking: AgendaBooking; triggerClassName?: string };

/** Deposits travel by phone long before the visit: Yape is the usual way. */
const DEPOSIT_METHOD = 'yape';

/** The deposit arrived: the booking is confirmed and the money recorded. */
export function DepositReceivedDialog({ booking, triggerClassName }: Props) {
    return (
        <PaymentMethodDialog
            trigger={
                <Button className={triggerClassName}>
                    <Check aria-hidden /> Seña recibida
                </Button>
            }
            title={
                booking.deposit
                    ? `¿Recibiste la seña de ${booking.client.name}?`
                    : `¿Confirmar la cita de ${booking.client.name}?`
            }
            description="La cita quedará confirmada y la seña anotada como recibida."
            amount={booking.deposit?.amount ?? null}
            legend="¿Cómo llegó?"
            defaultMethod={DEPOSIT_METHOD}
            confirmLabel="Sí, la recibí"
            url={confirm.url(booking.id)}
        />
    );
}

/** A cancelled visit whose deposit goes back to the client. */
export function DepositRefundDialog({ booking, triggerClassName }: Props) {
    return (
        <PaymentMethodDialog
            trigger={
                <Button variant="outline" className={triggerClassName}>
                    <Undo2 aria-hidden /> Devolver seña
                </Button>
            }
            title={`¿Devolviste la seña a ${booking.client.name}?`}
            description="Anótala cuando el dinero ya haya salido: quedará como una salida de caja."
            amount={booking.deposit?.amount ?? booking.depositPaid}
            legend="¿Cómo la devolviste?"
            defaultMethod={DEPOSIT_METHOD}
            confirmLabel="Sí, la devolví"
            url={refundDeposit.url(booking.id)}
        />
    );
}
