import { Head, Link } from '@inertiajs/react';
import { CalendarDays, ChevronRight, Repeat } from 'lucide-react';
import { BookAgainLink } from '@/components/booking/book-again-link';
import { ClientBookingCard } from '@/components/booking/client-booking-card';
import type { ClientBooking } from '@/components/booking/types';
import { BrandTheme } from '@/components/salon/brand-theme';
import { index } from '@/routes/bookings';
import { create as bookAgain } from '@/routes/salons/booking';

type Props = { bookings: ClientBooking[] };

/** With this few bookings the list offers the way to book another one. */
const FEW_BOOKINGS = 3;
/** Salons offered for it: the most recent ones. */
const SALONS_TO_OFFER = 2;

const SECTION_TITLE =
    'font-sans text-xs font-bold tracking-[.16em] text-muted-foreground uppercase';

function BookingList({ bookings }: Props) {
    return (
        <ul className="grid gap-4 lg:grid-cols-2 lg:gap-5">
            {bookings.map((booking) => (
                <li key={booking.code} className="min-w-0">
                    <ClientBookingCard booking={booking} />
                </li>
            ))}
        </ul>
    );
}

/** "Repetir mi última reserva": the newest visit that happened, one tap away. */
function RepeatLast({ booking }: { booking: ClientBooking }) {
    return (
        <BrandTheme brand={booking.salon.brand} theme={booking.salon.theme}>
            <Link
                href={bookAgain(booking.salon.slug, {
                    query: { repetir: booking.code },
                })}
                className="flex min-h-18 items-center gap-4 rounded-xl p-4 skin-raised transition-transform duration-150 outline-none hover:-translate-y-0.5 focus-visible:ring-[3px] focus-visible:ring-ring"
            >
                <span
                    aria-hidden
                    className="flex size-12 shrink-0 items-center justify-center rounded-full skin-primary"
                >
                    <Repeat className="size-5" />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block font-semibold">
                        Repetir mi última reserva
                    </span>
                    <span className="block truncate text-sm text-muted-foreground">
                        {booking.services
                            .map((service) => service.name)
                            .join(', ')}{' '}
                        · {booking.salon.name}
                    </span>
                </span>
                <ChevronRight
                    className="size-5 shrink-0 text-brand-text"
                    aria-hidden
                />
            </Link>
        </BrandTheme>
    );
}

export default function BookingsIndex({ bookings }: Props) {
    const isActive = (booking: ClientBooking) =>
        booking.isUpcoming &&
        (booking.status === 'pending' || booking.status === 'confirmed');
    const upcoming = bookings.filter(isActive).reverse();
    const past = bookings.filter((booking) => !isActive(booking));
    // The newest visit that actually happened.
    const last = past.find((booking) => booking.status === 'completed');
    const salons = [
        ...new Map(
            bookings.map((booking) => [booking.salon.slug, booking.salon]),
        ).values(),
    ].slice(0, SALONS_TO_OFFER);

    return (
        <>
            <Head title="Mis reservas" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 px-4 py-6 lg:py-10">
                <h1 className="text-3xl font-semibold lg:text-4xl">
                    Mis reservas
                </h1>

                {bookings.length === 0 && (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border-strong px-6 py-12 text-center">
                        <span
                            aria-hidden
                            className="flex size-14 items-center justify-center rounded-full bg-brand-soft text-brand-text"
                        >
                            <CalendarDays className="size-6" />
                        </span>
                        <p className="text-lg font-semibold">
                            Aún no tienes reservas
                        </p>
                        <p className="max-w-xs text-sm text-balance text-muted-foreground">
                            Abre la página de tu salón y toca "Reservar cita".
                            Tus reservas aparecerán aquí.
                        </p>
                    </div>
                )}

                {last && <RepeatLast booking={last} />}

                {upcoming.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className={SECTION_TITLE}>Próximas</h2>
                        <BookingList bookings={upcoming} />
                    </section>
                )}

                {past.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className={SECTION_TITLE}>Anteriores</h2>
                        <BookingList bookings={past} />
                    </section>
                )}

                {bookings.length > 0 && bookings.length <= FEW_BOOKINGS && (
                    <ul className="grid gap-3 lg:grid-cols-2 lg:gap-5">
                        {salons.map((salon) => (
                            <li key={salon.slug} className="min-w-0">
                                <BookAgainLink salon={salon} />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

BookingsIndex.layout = {
    breadcrumbs: [{ title: 'Mis reservas', href: index() }],
};
