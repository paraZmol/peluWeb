import { Link } from '@inertiajs/react';
import { CalendarPlus, History } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { create as newBooking } from '@/routes/agenda/bookings';

/** Nothing booked: the way to book is right there. */
export function NoUpcomingVisits() {
    return (
        <>
            <CalendarPlus className="size-6" aria-hidden />
            <p>No tiene citas próximas.</p>
            <Button asChild variant="outline">
                <Link href={newBooking()}>Agendar una cita</Link>
            </Button>
        </>
    );
}

/** No visit has ended yet: what the history will hold. */
export function NoPastVisits() {
    return (
        <>
            <History className="size-6" aria-hidden />
            <p>
                Todavía no hay visitas terminadas. Aquí verás qué se hizo, quién
                atendió y si faltó o canceló alguna vez.
            </p>
        </>
    );
}
