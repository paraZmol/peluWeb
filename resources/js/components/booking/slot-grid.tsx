import { CalendarX, Moon, Sun, Sunrise } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Slot } from './types';

const PERIODS = [
    { label: 'Mañana', icon: Sunrise, from: 0, to: 12 },
    { label: 'Tarde', icon: Sun, from: 12, to: 18 },
    { label: 'Noche', icon: Moon, from: 18, to: 24 },
];

type Props = {
    /** null while they load. */
    slots: Slot[] | null;
    error?: string;
    isLoading: boolean;
    retry: () => void;
    onPick: (slot: Slot) => void;
    /** Start of the slot to show as chosen, when picking does not move on. */
    selected?: number | null;
    empty: ReactNode;
};

/** The free times of a day as chips, grouped by morning, afternoon and night. */
export function SlotGrid({
    slots,
    error,
    isLoading,
    retry,
    onPick,
    selected = null,
    empty,
}: Props) {
    const reduceMotion = useReducedMotion();
    const inPeriod = (slot: Slot, from: number, to: number) => {
        const hour = Number(slot.time.slice(0, 2));

        return hour >= from && hour < to;
    };

    return (
        <>
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
                    {empty}
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
                            <h2 className="flex items-center gap-2 font-sans text-sm font-semibold text-muted-foreground">
                                <period.icon className="size-4" aria-hidden />
                                {period.label}
                            </h2>
                            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                {inside.map((slot, index) => (
                                    <motion.button
                                        key={slot.start}
                                        type="button"
                                        aria-pressed={
                                            selected === null
                                                ? undefined
                                                : selected === slot.start
                                        }
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
                                        onClick={() => onPick(slot)}
                                        className={cn(
                                            'h-12 rounded-full border text-sm font-medium tabular-nums transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-[0.97]',
                                            selected === slot.start
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'bg-card hover:border-primary hover:bg-primary/10',
                                        )}
                                    >
                                        {formatTime(slot.time)}
                                    </motion.button>
                                ))}
                            </div>
                        </section>
                    );
                })}
        </>
    );
}
