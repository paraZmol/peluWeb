import { Link } from '@inertiajs/react';
import { CalendarClock, MessageCircle, Navigation, Repeat } from 'lucide-react';
import { CHIP_ACTION, PRIMARY_ACTION } from '@/lib/skin-actions';
import { move, slots } from '@/routes/bookings';
import { create as bookAgain } from '@/routes/salons/booking';
import { MoveDialog } from './move-dialog';
import type { BookingDay, ClientBooking } from './types';

type Props = {
    booking: ClientBooking;
    days: BookingDay[];
    /** Still to come and not cancelled. */
    isActive: boolean;
    /** Another primary is on screen (the review): booking again steps back. */
    isRepeatSecondary?: boolean;
};

/**
 * What the client can do with a booking. One primary at a time: how to get
 * there while it is ahead, booking it again once it is over.
 */
export function BookingActions({
    booking,
    days,
    isActive,
    isRepeatSecondary = false,
}: Props) {
    const { salon } = booking;

    return (
        <div className="grid gap-2.5 sm:flex sm:flex-wrap">
            {isActive && salon.directionsUrl && (
                <a
                    href={salon.directionsUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={PRIMARY_ACTION}
                >
                    <Navigation aria-hidden /> Cómo llegar
                </a>
            )}
            {!isActive && (
                <Link
                    href={bookAgain(salon.slug, {
                        query: { repetir: booking.code },
                    })}
                    className={isRepeatSecondary ? CHIP_ACTION : PRIMARY_ACTION}
                >
                    <Repeat aria-hidden /> Repetir esta reserva
                </Link>
            )}
            {booking.canMove && (
                <MoveDialog
                    trigger={
                        <button type="button" className={CHIP_ACTION}>
                            <CalendarClock aria-hidden /> Cambiar día u hora
                        </button>
                    }
                    title="Cambiar día u hora"
                    confirmVerb="Cambiar"
                    description={`Con ${booking.staffName}. Los servicios y el precio no cambian.`}
                    days={days}
                    date={booking.date}
                    slotsUrl={(date) =>
                        slots.url(booking.code, { query: { date } })
                    }
                    moveUrl={move.url(booking.code)}
                />
            )}
            {salon.whatsappUrl && (
                <a
                    href={salon.whatsappUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={CHIP_ACTION}
                >
                    <MessageCircle aria-hidden /> Escribir al salón
                </a>
            )}
        </div>
    );
}
