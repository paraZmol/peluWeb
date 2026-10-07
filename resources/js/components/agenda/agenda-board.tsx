import { router } from '@inertiajs/react';
import { useState, useSyncExternalStore } from 'react';
import type {
    AgendaBooking,
    BookingDay,
    StaffOption,
} from '@/components/booking/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { GridColumn } from '@/hooks/use-grid-drag';
import { formatDate } from '@/lib/format';
import { move } from '@/routes/agenda/bookings';
import { AgendaItem } from './agenda-item';
import { TimeGrid } from './time-grid';

export type BoardView = 'tablero' | 'semana';

type Props = {
    view: BoardView;
    today: string;
    /** The seven days shown in the week view ("Y-m-d"). */
    weekDays: string[];
    bookings: AgendaBooking[];
    staffOptions: StaffOption[];
    hours: { from: number; to: number };
    slotStep: number;
    days: BookingDay[];
    /** Whoever runs every agenda may drop a visit on a colleague. */
    canReassign: boolean;
};

const FINE_POINTER = '(pointer: fine)';

function subscribeToPointer(callback: () => void) {
    const query = window.matchMedia(FINE_POINTER);
    query.addEventListener('change', callback);

    return () => query.removeEventListener('change', callback);
}

/** The agenda on a clock: a day by professional, or a week by day. */
export function AgendaBoard({
    view,
    today,
    weekDays,
    bookings,
    staffOptions,
    hours,
    slotStep,
    days,
    canReassign,
}: Props) {
    const [openedId, setOpenedId] = useState<number | null>(null);
    const opened = bookings.find((booking) => booking.id === openedId);
    const hasMouse = useSyncExternalStore(
        subscribeToPointer,
        () => window.matchMedia(FINE_POINTER).matches,
        () => false,
    );
    const byStaff = view === 'tablero';

    const columns: GridColumn[] = byStaff
        ? staffOptions.map((staff) => ({
              key: String(staff.id),
              label: staff.name,
          }))
        : weekDays.map((day) => ({
              key: day,
              label: formatDate(day),
              isCurrent: day === today,
          }));

    // The server turns the day and the time on the salon's clock into an instant.
    const onMove = (booking: AgendaBooking, column: string, time: string) =>
        router.post(
            move.url(booking.id),
            byStaff
                ? {
                      date: booking.date,
                      time,
                      staff: Number(column),
                      stay: true,
                  }
                : { date: column, time, stay: true },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <TimeGrid
                columns={columns}
                bookings={bookings}
                columnOf={(booking) =>
                    byStaff ? String(booking.staffId) : booking.date
                }
                hours={hours}
                step={slotStep}
                canChangeColumn={!byStaff || canReassign}
                canDrag={hasMouse}
                onMove={onMove}
                onOpen={(booking) => setOpenedId(booking.id)}
            />
            {hasMouse && (
                <p className="text-sm text-muted-foreground">
                    Arrastra una cita para moverla, o tócala para ver sus
                    opciones.
                </p>
            )}

            <Dialog
                open={opened !== undefined}
                onOpenChange={(open) => !open && setOpenedId(null)}
            >
                <DialogContent className="max-h-[90svh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Cita</DialogTitle>
                        <DialogDescription className="capitalize">
                            {opened && formatDate(opened.date)}
                        </DialogDescription>
                    </DialogHeader>
                    {opened && (
                        <AgendaItem
                            booking={opened}
                            showStaff
                            days={days}
                            staffOptions={staffOptions}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
