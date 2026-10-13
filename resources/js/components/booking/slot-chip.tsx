import { motion, useReducedMotion } from 'motion/react';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Slot } from './types';

/** Chips that enter one after the other; the rest arrive together. */
const STAGGERED = 8;

type Props = {
    slot: Slot;
    /** Position in its group, for the staggered entrance. */
    index: number;
    /** Start of the chosen slot; null when picking moves on right away. */
    selected: number | null;
    onPick: (slot: Slot) => void;
    className?: string;
};

/** One free time of the day. */
export function SlotChip({ slot, index, selected, onPick, className }: Props) {
    const reduceMotion = useReducedMotion();
    const isSelected = selected === slot.start;

    return (
        <motion.button
            type="button"
            aria-pressed={selected === null ? undefined : isSelected}
            initial={reduceMotion ? false : { opacity: 0, y: 6 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{
                duration: 0.15,
                delay: Math.min(index, STAGGERED) * 0.02,
            }}
            onClick={() => onPick(slot)}
            className={cn(
                'flex h-12 flex-col items-center justify-center gap-0.5 rounded-full text-sm leading-none font-bold tabular-nums transition-transform outline-none focus-visible:ring-[3px] focus-visible:ring-ring active:scale-[0.97]',
                isSelected ? 'skin-primary' : 'skin-chip',
                className,
            )}
        >
            {formatTime(slot.time)}{' '}
            {slot.promotion && (
                <span
                    className={cn(
                        'text-xs leading-none font-semibold',
                        !isSelected && 'text-brand-text',
                    )}
                >
                    con promo
                </span>
            )}
        </motion.button>
    );
}
