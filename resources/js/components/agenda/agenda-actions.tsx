import { router } from '@inertiajs/react';
import { CalendarClock, Check, MessageCircle, Play } from 'lucide-react';
import { MoveDialog } from '@/components/booking/move-dialog';
import type {
    AgendaBooking,
    BookingDay,
    StaffOption,
} from '@/components/booking/types';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import {
    cancel,
    complete,
    confirm,
    move,
    noShow,
    slots,
    start,
} from '@/routes/agenda/bookings';

type Props = {
    booking: AgendaBooking;
    /** Days of the picker for moving it. */
    days: BookingDay[];
    /** Colleagues it can be handed to; empty for a professional's own agenda. */
    staffOptions: StaffOption[];
};

/** What the viewer may do with a booking; the server decides the list. */
export function AgendaActions({ booking, days, staffOptions }: Props) {
    const can = (action: AgendaBooking['actions'][number]) =>
        booking.actions.includes(action);
    const post = (route: { url: string }) =>
        router.post(route.url, {}, { preserveScroll: true });

    return (
        <div className="flex flex-wrap items-center gap-2 [&_a]:h-11 sm:[&_a]:h-9 [&_button]:h-11 sm:[&_button]:h-9">
            {can('confirm') && (
                <Button onClick={() => post(confirm(booking.id))}>
                    <Check /> Seña recibida
                </Button>
            )}
            {can('start') && (
                <Button onClick={() => post(start(booking.id))}>
                    <Play /> Llegó
                </Button>
            )}
            {can('complete') && (
                <Button onClick={() => post(complete(booking.id))}>
                    <Check /> Terminé
                </Button>
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
            {can('move') && (
                <MoveDialog
                    trigger={
                        <Button variant="outline">
                            <CalendarClock aria-hidden /> Mover
                        </Button>
                    }
                    title={`Mover la cita de ${booking.client.name}`}
                    description="Elige el nuevo día y la hora. Los servicios y el precio no cambian."
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
                    title={`¿${booking.client.name} no vino?`}
                    description="Quedará anotado en su ficha y el horario se libera."
                    confirmLabel="Sí, no vino"
                    form={noShow.form(booking.id)}
                />
            )}
            {can('cancel') && (
                <ConfirmRemoveDialog
                    triggerLabel="Cancelar"
                    title="¿Cancelar esta cita?"
                    description="El horario quedará libre. Avísale a tu cliente."
                    confirmLabel="Sí, cancelar"
                    form={cancel.form(booking.id)}
                />
            )}
        </div>
    );
}
