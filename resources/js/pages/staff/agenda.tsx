import { Head, router } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { AgendaItem } from '@/components/agenda/agenda-item';
import type { AgendaBooking } from '@/components/booking/types';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/agenda';

type Props = {
    date: string;
    today: string;
    bookings: AgendaBooking[];
    staffOptions: { id: number; name: string }[];
    selectedStaffId: number | null;
    hasProfile: boolean;
    seesEveryone: boolean;
};

const EVERYONE = 'all';

function shift(date: string, days: number): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + days))
        .toISOString()
        .slice(0, 10);
}

export default function Agenda({
    date,
    today,
    bookings,
    staffOptions,
    selectedStaffId,
    hasProfile,
    seesEveryone,
}: Props) {
    const reduceMotion = useReducedMotion();
    const go = (fecha: string, profesional = selectedStaffId) =>
        router.get(
            index(),
            { fecha, ...(profesional ? { profesional } : {}) },
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Agenda" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-6">
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Día anterior"
                        onClick={() => go(shift(date, -1))}
                    >
                        <ChevronLeft />
                    </Button>
                    <div className="min-w-0 flex-1 text-center">
                        <h1 className="text-xl font-semibold capitalize">
                            {date === today ? 'Hoy' : formatDate(date)}
                        </h1>
                        {date === today && (
                            <p className="text-sm text-muted-foreground capitalize">
                                {formatDate(date)}
                            </p>
                        )}
                    </div>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Día siguiente"
                        onClick={() => go(shift(date, 1))}
                    >
                        <ChevronRight />
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {date !== today && (
                        <Button variant="outline" onClick={() => go(today)}>
                            Volver a hoy
                        </Button>
                    )}
                    {seesEveryone && staffOptions.length > 1 && (
                        <Select
                            value={selectedStaffId?.toString() ?? EVERYONE}
                            onValueChange={(value) =>
                                go(
                                    date,
                                    value === EVERYONE ? null : Number(value),
                                )
                            }
                        >
                            <SelectTrigger
                                className="w-auto min-w-44"
                                aria-label="Profesional"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={EVERYONE}>
                                    Todo el equipo
                                </SelectItem>
                                {staffOptions.map((staff) => (
                                    <SelectItem
                                        key={staff.id}
                                        value={staff.id.toString()}
                                    >
                                        {staff.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                {bookings.length === 0 ? (
                    <p className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        <CalendarDays className="size-6" aria-hidden />
                        {seesEveryone || hasProfile
                            ? 'Sin citas este día.'
                            : 'Aún no tienes un perfil de profesional en este salón, así que no hay citas a tu nombre.'}
                    </p>
                ) : (
                    <ul className="flex flex-col gap-3">
                        <AnimatePresence initial={false}>
                            {bookings.map((booking) => (
                                <motion.li
                                    key={booking.id}
                                    layout={!reduceMotion}
                                    initial={
                                        reduceMotion ? false : { opacity: 0 }
                                    }
                                    animate={{ opacity: 1 }}
                                    transition={{ duration: 0.2 }}
                                >
                                    <AgendaItem
                                        booking={booking}
                                        showStaff={
                                            seesEveryone &&
                                            selectedStaffId === null
                                        }
                                    />
                                </motion.li>
                            ))}
                        </AnimatePresence>
                    </ul>
                )}
            </div>
        </>
    );
}

Agenda.layout = {
    breadcrumbs: [{ title: 'Agenda', href: index() }],
};
