import { Head, router } from '@inertiajs/react';
import { AgendaBoard } from '@/components/agenda/agenda-board';
import { AgendaList } from '@/components/agenda/agenda-list';
import type { AgendaView } from '@/components/agenda/agenda-toolbar';
import { AgendaToolbar } from '@/components/agenda/agenda-toolbar';
import { TodaySummary } from '@/components/agenda/today-summary';
import type {
    AgendaBooking,
    BookingDay,
    StaffOption,
} from '@/components/booking/types';
import { cn } from '@/lib/utils';
import { index } from '@/routes/agenda';

type Props = {
    view: AgendaView;
    date: string;
    today: string;
    /** The seven days of the week view; empty in the others. */
    weekDays: string[];
    bookings: AgendaBooking[];
    staffOptions: StaffOption[];
    selectedStaffId: number | null;
    /** The week view picked that professional by itself; nobody asked for them. */
    staffIsDefault: boolean;
    hasProfile: boolean;
    seesEveryone: boolean;
    canBook: boolean;
    days: BookingDay[];
    hours: { from: number; to: number };
    slotStep: number;
};

export default function Agenda({
    view,
    date,
    today,
    weekDays,
    bookings,
    staffOptions,
    selectedStaffId,
    staffIsDefault,
    hasProfile,
    seesEveryone,
    canBook,
    days,
    hours,
    slotStep,
}: Props) {
    const go = (changes: {
        fecha?: string;
        profesional?: number | null;
        vista?: AgendaView;
    }) => {
        const next = {
            fecha: date,
            // Not carried along when the week chose it: back in the list,
            // "Todo el equipo" is what was being looked at.
            profesional: staffIsDefault ? null : selectedStaffId,
            vista: view,
            ...changes,
        };

        router.get(
            index(),
            {
                fecha: next.fecha,
                ...(next.profesional ? { profesional: next.profesional } : {}),
                ...(next.vista === 'lista' ? {} : { vista: next.vista }),
            },
            { preserveState: true, preserveScroll: true },
        );
    };
    const showStaff = seesEveryone && selectedStaffId === null;

    return (
        <>
            <Head title="Agenda" />

            <div
                className={cn(
                    'mx-auto flex w-full flex-col gap-5 px-4 py-6',
                    view === 'lista' ? 'max-w-3xl' : 'max-w-6xl',
                )}
            >
                <AgendaToolbar
                    view={view}
                    date={date}
                    today={today}
                    week={
                        view === 'semana' && weekDays.length > 0
                            ? { from: weekDays[0], to: weekDays.at(-1)! }
                            : null
                    }
                    staffOptions={staffOptions}
                    selectedStaffId={selectedStaffId}
                    seesEveryone={seesEveryone}
                    canBook={canBook}
                    onDate={(fecha) => go({ fecha })}
                    onStaff={(profesional) => go({ profesional })}
                    onView={(vista) => go({ vista })}
                />

                {view === 'lista' &&
                    date === today &&
                    (seesEveryone || hasProfile) && (
                        <TodaySummary
                            bookings={bookings}
                            showStaff={showStaff}
                        />
                    )}

                {view !== 'lista' ? (
                    <AgendaBoard
                        view={view}
                        today={today}
                        weekDays={weekDays}
                        bookings={bookings}
                        staffOptions={staffOptions}
                        hours={hours}
                        slotStep={slotStep}
                        days={days}
                        canReassign={seesEveryone}
                    />
                ) : (
                    <AgendaList
                        bookings={bookings}
                        showStaff={showStaff}
                        days={days}
                        staffOptions={staffOptions}
                        empty={
                            seesEveryone || hasProfile
                                ? 'Sin citas este día.'
                                : 'Aún no tienes un perfil de profesional en este salón, así que no hay citas a tu nombre.'
                        }
                    />
                )}
            </div>
        </>
    );
}

Agenda.layout = {
    breadcrumbs: [{ title: 'Agenda', href: index() }],
};
