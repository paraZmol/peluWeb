import { Head } from '@inertiajs/react';
import { BookingActions } from '@/components/booking/booking-actions';
import { BookingHero } from '@/components/booking/booking-hero';
import { BookingMapCard } from '@/components/booking/booking-map-card';
import { BookingSummary } from '@/components/booking/booking-summary';
import { DepositCard } from '@/components/booking/deposit-card';
import { ReferencePhoto } from '@/components/booking/reference-photo';
import type { BookingDay, ClientBooking } from '@/components/booking/types';
import { BrandTheme } from '@/components/salon/brand-theme';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { usePageTemplate } from '@/hooks/use-page-template';
import { CHIP_ACTION } from '@/lib/skin-actions';
import { cancel, index } from '@/routes/bookings';

type Props = { booking: ClientBooking; days: BookingDay[] };

/**
 * A booking in its salon's template. Phones: one column. Desktop: the
 * booking on the left, the map on the right.
 */
export default function BookingShow({ booking, days }: Props) {
    const { salon } = booking;
    const isActive =
        booking.isUpcoming &&
        (booking.status === 'confirmed' || booking.status === 'pending');

    usePageTemplate(salon);

    return (
        <BrandTheme
            brand={salon.brand}
            theme={salon.theme}
            className="flex-1 bg-background text-foreground"
        >
            <Head title={`Reserva en ${salon.name}`} />

            <div className="mx-auto grid w-full max-w-5xl grid-cols-1 gap-5 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:grid-rows-[auto_auto_1fr] lg:gap-x-8 lg:py-8">
                <BookingHero booking={booking} className="lg:col-span-2" />

                <div className="flex min-w-0 flex-col gap-5 lg:col-start-1">
                    {booking.deposit && (
                        <DepositCard deposit={booking.deposit} />
                    )}
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
                                <span className="font-semibold tracking-widest text-foreground">
                                    {booking.code}
                                </span>
                            </p>
                        }
                    />
                    <BookingActions
                        booking={booking}
                        days={days}
                        isActive={isActive}
                    />
                </div>

                <BookingMapCard
                    salon={salon}
                    className="lg:col-start-2 lg:row-span-2 lg:row-start-2 lg:self-start"
                />

                <div className="flex min-w-0 flex-col gap-5 lg:col-start-1">
                    <ReferencePhoto
                        code={booking.code}
                        url={booking.referencePhotoUrl}
                        canChange={booking.canAttachPhoto}
                    />
                    {booking.canCancel && (
                        <div className="flex items-center justify-between gap-3 border-t pt-5">
                            <p className="text-sm text-balance text-muted-foreground">
                                {booking.cancelForfeitsDeposit
                                    ? `Faltan menos de ${booking.cancelWindowHours} h: si cancelas, la seña no se devuelve.`
                                    : '¿No podrás ir? Avisa con tiempo.'}
                            </p>
                            <ConfirmRemoveDialog
                                themed
                                triggerLabel="Cancelar reserva"
                                trigger={
                                    <button
                                        type="button"
                                        className={CHIP_ACTION}
                                    >
                                        Cancelar reserva
                                    </button>
                                }
                                title="¿Cancelar esta reserva?"
                                description={
                                    booking.cancelForfeitsDeposit
                                        ? 'Tu horario quedará libre y la seña que enviaste no se devuelve.'
                                        : 'Tu horario quedará libre para otra persona.'
                                }
                                confirmLabel="Sí, cancelar mi reserva"
                                form={cancel.form(booking.code)}
                            />
                        </div>
                    )}
                </div>
            </div>
        </BrandTheme>
    );
}

BookingShow.layout = {
    breadcrumbs: [{ title: 'Mis reservas', href: index() }],
};
