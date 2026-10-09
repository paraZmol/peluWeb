import { Link } from '@inertiajs/react';
import { CalendarPlus, ChevronRight } from 'lucide-react';
import { create as bookAgain } from '@/routes/salons/booking';
import type { ClientBooking } from './types';

/**
 * "Reservar otra cita": the way back to a salon the client already knows,
 * so a short list of bookings does not end in a dead end. In the platform's
 * look, like the list around it.
 */
export function BookAgainLink({ salon }: { salon: ClientBooking['salon'] }) {
    return (
        <Link
            href={bookAgain(salon.slug)}
            className="flex min-h-16 items-center gap-4 rounded-xl border border-dashed border-border-strong px-4 py-3 transition-colors outline-none hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring"
        >
            <span
                aria-hidden
                className="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-text"
            >
                <CalendarPlus className="size-5" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block font-semibold">Reservar otra cita</span>
                <span className="block truncate text-sm text-muted-foreground">
                    en {salon.name}
                </span>
            </span>
            <ChevronRight
                className="size-5 shrink-0 text-muted-foreground"
                aria-hidden
            />
        </Link>
    );
}
