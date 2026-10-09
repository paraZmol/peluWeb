import { CalendarX, Moon, RotateCw, Sun, Sunrise } from 'lucide-react';
import type { ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { CHIP_ACTION } from '@/lib/skin-actions';
import { NextSlot } from './next-slot';
import { SLOT_GRID, SlotPeriod } from './slot-period';
import type { Period } from './slot-period';
import type { Slot } from './types';

const PERIODS: Period[] = [
    {
        label: 'Mañana',
        ofLabel: 'de la mañana',
        icon: Sunrise,
        from: 0,
        to: 12,
    },
    { label: 'Tarde', ofLabel: 'de la tarde', icon: Sun, from: 12, to: 18 },
    { label: 'Noche', ofLabel: 'de la noche', icon: Moon, from: 18, to: 24 },
];

function isInside(slot: Slot, period: Period): boolean {
    const hour = Number(slot.time.slice(0, 2));

    return hour >= period.from && hour < period.to;
}

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

/**
 * The free times of a day: the earliest one on its own, then chips grouped
 * by morning, afternoon and night. A day may bring ninety of them, so each
 * period shows a few and unfolds; the one with the earliest time starts open.
 */
export function SlotGrid({
    slots,
    error,
    isLoading,
    retry,
    onPick,
    selected = null,
    empty,
}: Props) {
    return (
        <>
            {isLoading && (
                <div role="status" className="flex flex-col gap-3">
                    <span className="sr-only">Cargando horarios…</span>
                    <Skeleton className="h-5 w-24 rounded-full" />
                    <div className={SLOT_GRID}>
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

            {slots && slots.length > 1 && (
                <NextSlot slot={slots[0]} selected={selected} onPick={onPick} />
            )}

            {slots &&
                PERIODS.map((period) => {
                    const inside = slots.filter((slot) =>
                        isInside(slot, period),
                    );

                    return inside.length === 0 ? null : (
                        <SlotPeriod
                            // A new day starts over: folded, earliest open.
                            key={`${period.label}-${slots[0].start}`}
                            period={period}
                            slots={inside}
                            selected={selected}
                            onPick={onPick}
                            defaultOpen={isInside(slots[0], period)}
                        />
                    );
                })}
        </>
    );
}
