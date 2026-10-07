import { Head, Link } from '@inertiajs/react';
import { CalendarDays, ChevronRight } from 'lucide-react';
import { StatusBadge } from '@/components/booking/status-badge';
import type { ClientBooking } from '@/components/booking/types';
import { formatDate, formatTime } from '@/lib/format';
import { index, show } from '@/routes/bookings';

type Props = { bookings: ClientBooking[] };

function BookingList({ bookings }: Props) {
    return (
        <ul className="divide-y rounded-xl border bg-card">
            {bookings.map((booking) => (
                <li key={booking.code}>
                    <Link
                        href={show(booking.code)}
                        className="flex min-h-16 items-center gap-3 p-4 transition-colors hover:bg-accent"
                    >
                        <div className="min-w-0 flex-1">
                            <p className="font-medium break-words">
                                {booking.salon.name}
                            </p>
                            <p className="text-sm text-muted-foreground tabular-nums">
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
                        />
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

    return (
        <>
            <Head title="Mis reservas" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-6">
                <h1 className="text-2xl font-semibold">Mis reservas</h1>

                {bookings.length === 0 && (
                    <p className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        <CalendarDays className="size-6" aria-hidden />
                        Aún no tienes reservas. Abre la página de tu salón y
                        toca "Reservar".
                    </p>
                )}

                {upcoming.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="text-lg font-semibold">Próximas</h2>
                        <BookingList bookings={upcoming} />
                    </section>
                )}

                {past.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="text-lg font-semibold">Anteriores</h2>
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
