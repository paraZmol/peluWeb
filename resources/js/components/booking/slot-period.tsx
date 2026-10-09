import { ChevronDown } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useId, useState } from 'react';
import { cn } from '@/lib/utils';
import { SlotChip } from './slot-chip';
import type { Slot } from './types';

export const SLOT_GRID = 'grid grid-cols-3 gap-2.5 sm:grid-cols-4';

/** Chips of a folded period: three rows on a phone. */
const FOLDED = 9;
/** A period this short is shown whole: "ver más" would reveal too little. */
const FOLD_OVER = 12;
/** The four-column grid shows two full rows; the ninth chip would hang alone. */
const HIDE_LAST_ON_WIDE = 'sm:hidden';

export type Period = {
    label: string;
    /** Ends "Ver más horarios …". */
    ofLabel: string;
    icon: LucideIcon;
    from: number;
    to: number;
};

type Props = {
    period: Period;
    slots: Slot[];
    selected: number | null;
    onPick: (slot: Slot) => void;
    defaultOpen: boolean;
};

/** The first chips, always with the chosen time among them. */
function fold(slots: Slot[], selected: number | null): Slot[] {
    const chosen = slots.slice(FOLDED).find((slot) => slot.start === selected);

    return chosen
        ? [...slots.slice(0, FOLDED - 1), chosen]
        : slots.slice(0, FOLDED);
}

/** Morning, afternoon or night: a few times, and the rest one tap away. */
export function SlotPeriod({
    period,
    slots,
    selected,
    onPick,
    defaultOpen,
}: Props) {
    const [isOpen, setIsOpen] = useState(defaultOpen);
    const gridId = useId();
    const canFold = slots.length > FOLD_OVER;
    const isFolded = canFold && !isOpen;
    const visible = isFolded ? fold(slots, selected) : slots;

    return (
        <section className="flex flex-col gap-2.5">
            <h2 className="flex items-center gap-2 font-sans text-sm font-bold">
                <period.icon className="size-4.5 text-brand-text" aria-hidden />
                {period.label}
                <span className="ml-auto text-sm font-medium text-muted-foreground tabular-nums">
                    {slots.length === 1
                        ? '1 horario'
                        : `${slots.length} horarios`}
                </span>
            </h2>
            <div id={gridId} className={SLOT_GRID}>
                {visible.map((slot, index) => (
                    <SlotChip
                        key={slot.start}
                        slot={slot}
                        index={index}
                        selected={selected}
                        onPick={onPick}
                        className={cn(
                            isFolded &&
                                index === FOLDED - 1 &&
                                slot.start !== selected &&
                                HIDE_LAST_ON_WIDE,
                        )}
                    />
                ))}
            </div>
            {canFold && (
                <button
                    type="button"
                    aria-expanded={isOpen}
                    aria-controls={gridId}
                    onClick={() => setIsOpen((open) => !open)}
                    className="inline-flex h-11 items-center justify-center gap-1.5 self-center rounded-full px-4 text-sm font-semibold text-brand-text outline-none focus-visible:ring-[3px] focus-visible:ring-ring"
                >
                    {isOpen ? 'Ver menos' : 'Ver más'} horarios {period.ofLabel}
                    <ChevronDown
                        className={cn(
                            'size-4 transition-transform duration-200 motion-reduce:transition-none',
                            isOpen && 'rotate-180',
                        )}
                        aria-hidden
                    />
                </button>
            )}
        </section>
    );
}
