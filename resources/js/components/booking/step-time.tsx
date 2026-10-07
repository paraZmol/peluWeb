import { CalendarX } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { useAvailability } from '@/hooks/use-availability';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { formatTime } from '@/lib/format';
import { DayStrip } from './day-strip';
import type { BookingDay, Slot } from './types';

const PERIODS = [
    { label: 'Mañana', from: 0, to: 12 },
    { label: 'Tarde', from: 12, to: 18 },
    { label: 'Noche', from: 18, to: 24 },
];

type Props = {
    days: BookingDay[];
    wizard: BookingWizard;
    availability: ReturnType<typeof useAvailability>;
};

export function StepTime({ days, wizard, availability }: Props) {
    const reduceMotion = useReducedMotion();
    const firstOpen = days.find((day) => !day.closed)?.date ?? null;
    const date = wizard.date ?? firstOpen;
    const { slots, error, isLoading, retry } = availability;
    const { chooseDate } = wizard;

    // Start on the first day the salon opens, so the list is never empty by default.
    useEffect(() => {
        if (wizard.date === null && firstOpen !== null) {
            chooseDate(firstOpen);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [wizard.date, firstOpen]);

    if (firstOpen === null) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                Por ahora no hay días disponibles para reservar.
            </p>
        );
    }

    const inPeriod = (slot: Slot, from: number, to: number) => {
        const hour = Number(slot.time.slice(0, 2));

        return hour >= from && hour < to;
    };

    return (
        <div className="flex flex-col gap-5">
            <DayStrip days={days} value={date} onChange={wizard.chooseDate} />

            {isLoading && (
                <div
                    className="grid grid-cols-3 gap-2 sm:grid-cols-4"
                    aria-busy
                    aria-label="Cargando horarios"
                >
                    {Array.from({ length: 9 }, (_, i) => (
                        <Skeleton key={i} className="h-12 rounded-full" />
                    ))}
                </div>
            )}

            {error && (
                <div
                    role="alert"
                    className="flex flex-col items-center gap-3 rounded-xl border p-6 text-center"
                >
                    <p className="text-sm text-muted-foreground">{error}</p>
                    <Button variant="outline" onClick={retry}>
                        Reintentar
                    </Button>
                </div>
            )}

            {slots?.length === 0 && (
                <p className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                    <CalendarX className="size-6" aria-hidden />
                    No quedan horarios este día. Prueba con otro día
                    {wizard.staffId !== null && ' o con otra persona'}.
                </p>
            )}

            {slots &&
                PERIODS.map((period) => {
                    const inside = slots.filter((slot) =>
                        inPeriod(slot, period.from, period.to),
                    );

                    return inside.length === 0 ? null : (
                        <section
                            key={period.label}
                            className="flex flex-col gap-2"
                        >
                            <h2 className="text-sm font-semibold text-muted-foreground">
                                {period.label}
                            </h2>
                            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                {inside.map((slot, index) => (
                                    <motion.button
                                        key={slot.start}
                                        type="button"
                                        initial={
                                            reduceMotion
                                                ? false
                                                : { opacity: 0, y: 6 }
                                        }
                                        animate={{ opacity: 1, y: 0 }}
                                        transition={{
                                            duration: 0.15,
                                            delay: Math.min(index, 8) * 0.02,
                                        }}
                                        onClick={() => wizard.chooseSlot(slot)}
                                        className="h-12 rounded-full border bg-card text-sm font-medium tabular-nums transition-colors hover:border-primary hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {formatTime(slot.time)}
                                    </motion.button>
                                ))}
                            </div>
                        </section>
                    );
                })}
        </div>
    );
}
