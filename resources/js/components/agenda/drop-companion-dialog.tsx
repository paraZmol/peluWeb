import { router } from '@inertiajs/react';
import { UserMinus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { AgendaBooking } from '@/components/booking/types';
import { RadioPills } from '@/components/form/radio-pills';
import { usePortalTheme } from '@/components/salon/portal-theme';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import { dropCompanion } from '@/routes/agenda/bookings';

type Props = {
    booking: AgendaBooking;
    /** Who was coming along, on the second line. */
    companion: string;
};

/** As the server reads it: the one who booked, or the one coming along. */
type Came = 'booker' | 'companion';

/**
 * "Ven con alguien" when only one of them came: the team says who, the
 * other line goes and the promotion stops applying.
 */
export function DropCompanionDialog({ booking, companion }: Props) {
    const portalTheme = usePortalTheme();
    const [came, setCame] = useState<Came>('booker');
    const [sending, setSending] = useState(false);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.post(
            dropCompanion.url(booking.id),
            { came },
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <UserMinus aria-hidden /> Llegó una sola persona
                </Button>
            </DialogTrigger>
            <DialogContent
                {...portalTheme}
                className={cn(portalTheme.className, SHEET_ON_PHONE)}
            >
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader className="pr-8 text-left">
                        <DialogTitle>¿Quién vino?</DialogTitle>
                        <DialogDescription>
                            La promoción deja de aplicar. Se cobra a precio
                            normal solo el servicio de quien vino. La seña
                            pagada se descuenta. Díselo antes de empezar.
                            Quedará registrado que lo hiciste tú.
                        </DialogDescription>
                    </DialogHeader>
                    <RadioPills
                        legend="Vino"
                        name="came"
                        columns={2}
                        options={[
                            { value: 'booker', label: booking.client.name },
                            { value: 'companion', label: companion },
                        ]}
                        value={came}
                        onChange={(value) => setCame(value as Came)}
                    />
                    <DialogFooter className="gap-2 [&_button]:h-12 sm:[&_button]:h-10">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Volver
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={sending}>
                            {sending && <Spinner />}
                            Quitar y cobrar precio normal
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
