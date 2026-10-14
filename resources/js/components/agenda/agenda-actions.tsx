import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarClock, MessageCircle, Play, UserRound } from 'lucide-react';
import { CheckoutDialog } from '@/components/accounting/checkout-dialog';
import {
    DepositReceivedDialog,
    DepositRefundDialog,
} from '@/components/accounting/deposit-dialogs';
import { MoveDialog } from '@/components/booking/move-dialog';
import type {
    AgendaBooking,
    BookingDay,
    StaffOption,
} from '@/components/booking/types';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { DropCompanionDialog } from './drop-companion-dialog';
import { cancel, move, noShow, slots, start } from '@/routes/agenda/bookings';
import { show as clientFile } from '@/routes/clients';

type Props = {
    booking: AgendaBooking;
    /** Days of the picker for moving it. */
    days: BookingDay[];
    /** Colleagues it can be handed to; empty for a professional's own agenda. */
    staffOptions: StaffOption[];
};

/** A visit not over yet: someone may still turn out to come alone. */
const OPEN = ['pending', 'confirmed', 'in_service'];

/** The next step of a visit takes the whole row on a phone. */
const PRIMARY = 'col-span-2';

/** What the viewer may do with a booking; the server decides the list. */
export function AgendaActions({ booking, days, staffOptions }: Props) {
    const can = (action: AgendaBooking['actions'][number]) =>
        booking.actions.includes(action);
    // One tap, one request: a second tap on a slow connection would act
    // on whatever button takes this one's place.
    const [busy, setBusy] = useState(false);
    const post = (route: { url: string }) =>
        router.post(
            route.url,
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );

    return (
        <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center [&_a]:h-11 sm:[&_a]:h-9 [&_button]:h-11 sm:[&_button]:h-9">
            {can('confirm') && (
                <DepositReceivedDialog
                    booking={booking}
                    triggerClassName={PRIMARY}
                />
            )}
            {can('start') && (
                <Button
                    className={PRIMARY}
                    disabled={busy}
                    onClick={() => post(start(booking.id))}
                >
                    {busy ? <Spinner /> : <Play />} Llegó
                </Button>
            )}
            {can('complete') && (
                <CheckoutDialog booking={booking} triggerClassName={PRIMARY} />
            )}
            {can('refundDeposit') && (
                <DepositRefundDialog
                    booking={booking}
                    triggerClassName={PRIMARY}
                />
            )}
            {booking.companion &&
                OPEN.includes(booking.status) &&
                booking.actions.length > 0 && (
                    <DropCompanionDialog
                        booking={booking}
                        companion={booking.companion}
                    />
                )}
            {booking.client.whatsappUrl && (
                <Button asChild variant="outline">
                    <a
                        href={booking.client.whatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={`Escribir a ${booking.client.name} por WhatsApp`}
                    >
                        <MessageCircle aria-hidden /> WhatsApp
                    </a>
                </Button>
            )}
            <Button asChild variant="outline">
                <Link
                    href={clientFile(booking.client.id)}
                    aria-label={`Ver ficha de ${booking.client.name}`}
                >
                    <UserRound aria-hidden /> Ver ficha
                </Link>
            </Button>
            {can('move') && (
                <MoveDialog
                    trigger={
                        <Button variant="outline">
                            <CalendarClock aria-hidden /> Mover
                        </Button>
                    }
                    title="Mover cita"
                    description={`De ${booking.client.name}. Los servicios y el precio no cambian.`}
                    days={days}
                    date={booking.date}
                    staffOptions={staffOptions}
                    staffId={booking.staffId}
                    slotsUrl={(date, staff) =>
                        slots.url(booking.id, { query: { date, staff } })
                    }
                    moveUrl={move.url(booking.id)}
                />
            )}
            {can('noShow') && (
                <ConfirmRemoveDialog
                    triggerLabel="No vino"
                    title={`¿Marcar que ${booking.client.name} no vino?`}
                    description="Quedará anotado en su ficha y el horario se libera."
                    confirmLabel="Sí, no vino"
                    form={noShow.form(booking.id)}
                />
            )}
            {can('cancel') && (
                <ConfirmRemoveDialog
                    triggerLabel="Cancelar cita"
                    title="¿Cancelar esta cita?"
                    description="El horario quedará libre. Escribe a tu cliente para avisarle."
                    confirmLabel="Sí, cancelar la cita"
                    form={cancel.form(booking.id)}
                />
            )}
        </div>
    );
}
