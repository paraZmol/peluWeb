import { Head, Link } from '@inertiajs/react';
import { CalendarDays, ChevronRight, Repeat } from 'lucide-react';
import { DateTile } from '@/components/booking/date-tile';
import { StatusBadge } from '@/components/booking/status-badge';
import type { ClientBooking } from '@/components/booking/types';
import { formatDate, formatTime } from '@/lib/format';
import { index, show } from '@/routes/bookings';
import { create as bookAgain } from '@/routes/salons/booking';

type Props = { bookings: ClientBooking[] };

function BookingList({ bookings }: Props) {
    return (
        <ul className="divide-y overflow-hidden rounded-xl border bg-card">
            {bookings.map((booking) => (
                <li key={booking.code}>
                    <Link
                        href={show(booking.code)}
                        className="flex min-h-16 items-center gap-3 p-4 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                    >
                        <DateTile date={booking.date} />
                        <div className="flex min-w-0 flex-1 flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-3">
                            <div className="min-w-0 flex-1">
                                <p className="font-medium break-words">
                                    {booking.salon.name}
                                </p>
                                <p className="truncate text-sm text-muted-foreground tabular-nums">
                                    <span className="capitalize">
                                        {formatDate(booking.date)}
                                    </span>{' '}
                                    · {formatTime(booking.time)}
                                </p>
                                <p className="truncate text-sm text-muted-foreground">
                                    {booking.services
                                        .map((service) => service.name)
                                        .join(', ')}
                                </p>
                            </div>
                            <StatusBadge
                                status={booking.status}
                                label={booking.statusLabel}
                                className="shrink-0"
                            />
                        </div>
                        <ChevronRight
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden
                        />
                    </Link>
                </li>
            ))}
        </ul>
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

    return (
        <>
            <Head title="Mis reservas" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-6">
                <h1 className="text-2xl font-semibold sm:text-3xl">
                    Mis reservas
                </h1>

                {bookings.length === 0 && (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-10 text-center">
                        <span
                            aria-hidden
                            className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
                        >
                            <CalendarDays className="size-6" />
                        </span>
                        <p className="font-medium">Aún no tienes reservas</p>
                        <p className="max-w-xs text-sm text-balance text-muted-foreground">
                            Abre la página de tu salón y toca "Reservar". Tus
                            citas aparecerán aquí.
                        </p>
                    </div>
                )}

                {last && (
                    <Link
                        href={bookAgain(last.salon.slug, {
                            query: { repetir: last.code },
                        })}
                        className="flex min-h-16 items-center gap-3 rounded-xl border bg-card p-4 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span
                            aria-hidden
                            className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary"
                        >
                            <Repeat className="size-5" />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block font-medium">
                                Repetir mi última cita
                            </span>
                            <span className="block truncate text-sm text-muted-foreground">
                                {last.services
                                    .map((service) => service.name)
                                    .join(', ')}{' '}
                                · {last.salon.name}
                            </span>
                        </span>
                        <ChevronRight
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden
                        />
                    </Link>
                )}

                {upcoming.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="font-sans text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Próximas
                        </h2>
                        <BookingList bookings={upcoming} />
                    </section>
                )}

                {past.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="font-sans text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            Anteriores
                        </h2>
                        <BookingList bookings={past} />
                    </section>
                )}
            </div>
        </>
    );
}

BookingsIndex.layout = {
    breadcrumbs: [{ title: 'Mis reservas', href: index() }],
};
