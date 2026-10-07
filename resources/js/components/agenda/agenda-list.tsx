import { CalendarDays } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import type {
    AgendaBooking,
    BookingDay,
    StaffOption,
} from '@/components/booking/types';
import { AgendaItem } from './agenda-item';

type Props = {
    bookings: AgendaBooking[];
    showStaff: boolean;
    days: BookingDay[];
    staffOptions: StaffOption[];
    /** What to say when the day has no visits. */
    empty: string;
};

/** The visits of one day, top to bottom, each with its actions. */
export function AgendaList({
    bookings,
    showStaff,
    days,
    staffOptions,
    empty,
}: Props) {
    const reduceMotion = useReducedMotion();

    if (bookings.length === 0) {
        return (
            <p className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                <CalendarDays className="size-6" aria-hidden />
                {empty}
            </p>
        );
    }

    return (
        <ul className="flex flex-col gap-3">
            <AnimatePresence initial={false}>
                {bookings.map((booking) => (
                    <motion.li
                        key={booking.id}
                        layout={!reduceMotion}
                        initial={reduceMotion ? false : { opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ duration: 0.2 }}
                    >
                        <AgendaItem
                            booking={booking}
                            showStaff={showStaff}
                            days={days}
                            staffOptions={staffOptions}
                        />
                    </motion.li>
                ))}
            </AnimatePresence>
        </ul>
    );
}
