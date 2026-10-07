import { router } from '@inertiajs/react';
import { Check, MessageCircle, Play, Star } from 'lucide-react';
import { StatusBadge } from '@/components/booking/status-badge';
import type { AgendaBooking } from '@/components/booking/types';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import { formatPrice, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    cancel,
    complete,
    confirm,
    noShow,
    start,
} from '@/routes/agenda/bookings';

type Props = { booking: AgendaBooking; showStaff: boolean };

const DIMMED = ['cancelled', 'no_show', 'completed'];

export function AgendaItem({ booking, showStaff }: Props) {
    const can = (action: AgendaBooking['actions'][number]) =>
        booking.actions.includes(action);
    const post = (route: { url: string }) =>
        router.post(route.url, {}, { preserveScroll: true });

    return (
        <article
            aria-label={`${booking.client.name}, ${formatTime(booking.time)}`}
            className={cn(
                'flex flex-col gap-3 rounded-xl border bg-card p-4',
                booking.status === 'in_service' && 'border-primary',
                DIMMED.includes(booking.status) && 'opacity-60',
            )}
        >
            <div className="flex items-start gap-3">
                <p className="w-20 shrink-0 font-semibold tabular-nums">
                    {formatTime(booking.time)}
                    <span className="block text-xs font-normal text-muted-foreground">
                        hasta {formatTime(booking.endTime)}
                    </span>
                </p>
                <div className="min-w-0 flex-1">
                    <p className="flex flex-wrap items-center gap-2 font-medium break-words">
                        {booking.client.name}
                        {booking.isVip && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-primary/15 px-2 py-0.5 text-xs text-primary">
                                <Star className="size-3" aria-hidden /> VIP
                            </span>
                        )}
                    </p>
                    <p className="text-sm break-words text-muted-foreground">
                        {booking.services.join(', ')} ·{' '}
                        <span className="tabular-nums">
                            {formatPrice(booking.price)}
                        </span>
                    </p>
                    {showStaff && (
                        <p className="text-sm text-muted-foreground">
                            Con {booking.staffName}
                        </p>
                    )}
                    {booking.note && (
                        <p className="mt-1 text-sm break-words italic">
                            "{booking.note}"
                        </p>
                    )}
                    {booking.client.noShows > 0 && (
                        <p className="mt-1 text-xs text-warning">
                            Faltó {booking.client.noShows}{' '}
                            {booking.client.noShows === 1 ? 'vez' : 'veces'}{' '}
                            antes
                        </p>
                    )}
                </div>
                <StatusBadge
                    status={booking.status}
                    label={booking.statusLabel}
                />
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

            <div className="flex flex-wrap items-center gap-2">
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
        </article>
    );
}
