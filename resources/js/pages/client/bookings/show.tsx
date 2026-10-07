import { Head, Link } from '@inertiajs/react';
import { MessageCircle, Navigation } from 'lucide-react';
import { BookingSummary } from '@/components/booking/booking-summary';
import { DepositCard } from '@/components/booking/deposit-card';
import { StatusBadge } from '@/components/booking/status-badge';
import { SuccessCheck } from '@/components/booking/success-check';
import type { ClientBooking } from '@/components/booking/types';
import { BrandTheme } from '@/components/salon/brand-theme';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import { cancel, index } from '@/routes/bookings';
import { show as salonPage } from '@/routes/salons';

type Props = { booking: ClientBooking };

export default function BookingShow({ booking }: Props) {
    const { salon } = booking;
    const isActive =
        booking.isUpcoming &&
        (booking.status === 'confirmed' || booking.status === 'pending');

    return (
        <BrandTheme brand={salon.brand}>
            <Head title={`Reserva en ${salon.name}`} />

            <div className="mx-auto flex w-full max-w-xl flex-col gap-5 px-4 py-6">
                <header className="flex flex-col items-center gap-3 text-center">
                    {booking.status === 'confirmed' && booking.isUpcoming && (
                        <SuccessCheck />
                    )}
                    <h1 className="font-display text-2xl font-semibold text-balance sm:text-3xl">
                        {salon.name}
                    </h1>
                    <StatusBadge
                        status={booking.status}
                        label={booking.statusLabel}
                    />
                </header>

                {booking.deposit && <DepositCard deposit={booking.deposit} />}

                <BookingSummary
                    date={booking.date}
                    time={booking.time}
                    endTime={booking.endTime}
                    staffName={booking.staffName}
                    address={salon.address}
                    items={booking.services}
                    total={booking.price}
                    footer={
                        <p className="text-xs text-muted-foreground">
                            Código de reserva:{' '}
                            <span className="font-medium tracking-widest text-foreground">
                                {booking.code}
                            </span>
                        </p>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    {isActive && salon.directionsUrl && (
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-full"
                        >
                            <a
                                href={salon.directionsUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Navigation aria-hidden /> Cómo llegar
                            </a>
                        </Button>
                    )}
                    {salon.whatsappUrl && (
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-full"
                        >
                            <a
                                href={salon.whatsappUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <MessageCircle aria-hidden /> Escribir al salón
                            </a>
                        </Button>
                    )}
                    {!isActive && (
                        <Button asChild className="rounded-full">
                            <Link href={salonPage(salon.slug)}>
                                Reservar de nuevo
                            </Link>
                        </Button>
                    )}
                </div>

                {booking.canCancel && (
                    <div className="flex items-center justify-between gap-3 border-t pt-4">
                        <p className="text-sm text-balance text-muted-foreground">
                            {booking.cancelForfeitsDeposit
                                ? `Faltan menos de ${booking.cancelWindowHours} h: si cancelas, la seña no se devuelve.`
                                : '¿No podrás ir? Avisa con tiempo.'}
                        </p>
                        <ConfirmRemoveDialog
                            triggerLabel="Cancelar reserva"
                            title="¿Cancelar esta reserva?"
                            description={
                                booking.cancelForfeitsDeposit
                                    ? 'Tu horario quedará libre y la seña que enviaste no se devuelve.'
                                    : 'Tu horario quedará libre para otra persona.'
                            }
                            confirmLabel="Sí, cancelar"
                            form={cancel.form(booking.code)}
                        />
                    </div>
                )}
            </div>
        </BrandTheme>
    );
}

BookingShow.layout = {
    breadcrumbs: [{ title: 'Mis reservas', href: index() }],
};
