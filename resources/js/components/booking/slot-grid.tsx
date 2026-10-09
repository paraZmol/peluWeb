import { CalendarX, Moon, RotateCw, Sun, Sunrise } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { formatTime } from '@/lib/format';
import { CHIP_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import type { Slot } from './types';

const PERIODS = [
    { label: 'Mañana', icon: Sunrise, from: 0, to: 12 },
    { label: 'Tarde', icon: Sun, from: 12, to: 18 },
    { label: 'Noche', icon: Moon, from: 18, to: 24 },
];

const GRID = 'grid grid-cols-3 gap-2.5 sm:grid-cols-4';

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
                <div role="status" className="flex flex-col gap-3">
                    <span className="sr-only">Cargando horarios…</span>
                    <Skeleton className="h-5 w-24 rounded-full" />
                    <div className={GRID}>
                        {Array.from({ length: 9 }, (_, i) => (
                            <Skeleton key={i} className="h-12 rounded-full" />
                        ))}
                    </div>
                </div>
            )}

            {error && (
                <div
                    role="alert"
                    className="flex flex-col items-center gap-3 rounded-xl p-6 text-center skin-card"
                >
                    <p className="text-sm text-muted-foreground">{error}</p>
                    <button
                        type="button"
                        className={CHIP_ACTION}
                        onClick={retry}
                    >
                        <RotateCw aria-hidden /> Reintentar
                    </button>
                </div>
            )}

            {slots?.length === 0 && (
                <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border-strong p-8 text-center text-muted-foreground">
                    <CalendarX className="size-6 text-brand-text" aria-hidden />
                    {empty}
                </div>
            )}

            {slots &&
                PERIODS.map((period) => {
                    const inside = slots.filter((slot) =>
                        inPeriod(slot, period.from, period.to),
                    );

                    return inside.length === 0 ? null : (
                        <section
                            key={period.label}
                            className="flex flex-col gap-2.5"
                        >
                            <h2 className="flex items-center gap-2 font-sans text-sm font-bold">
                                <period.icon
                                    className="size-4.5 text-brand-text"
                                    aria-hidden
                                />
                                {period.label}
                                <span className="ml-auto text-sm font-medium text-muted-foreground tabular-nums">
                                    {inside.length === 1
                                        ? '1 horario'
                                        : `${inside.length} horarios`}
                                </span>
                            </h2>
                            <div className={GRID}>
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
                                            'h-12 rounded-full text-sm font-bold tabular-nums transition-transform outline-none focus-visible:ring-[3px] focus-visible:ring-ring/60 active:scale-[0.97]',
                                            selected === slot.start
                                                ? 'skin-primary'
                                                : 'skin-chip',
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
