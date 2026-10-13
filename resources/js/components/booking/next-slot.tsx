import { ArrowRight, Check, Zap } from 'lucide-react';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Slot } from './types';

type Props = {
    slot: Slot;
    /** Start of the chosen slot; null when picking moves on right away. */
    selected: number | null;
    onPick: (slot: Slot) => void;
};

/**
 * The earliest free time of the day, on its own above the list: most people
 * want "as soon as possible" and should not have to read ninety chips.
 */
export function NextSlot({ slot, selected, onPick }: Props) {
    const isSelected = selected === slot.start;
    const time = formatTime(slot.time);

    return (
        <button
            type="button"
            aria-label={`Próximo horario libre: ${time}${slot.promotion ? ', con promo' : ''}`}
            aria-pressed={selected === null ? undefined : isSelected}
            onClick={() => onPick(slot)}
            className={cn(
                'flex min-h-16 w-full items-center gap-3 rounded-xl p-3 pr-4 text-left transition-transform duration-150 outline-none focus-visible:ring-[3px] focus-visible:ring-ring active:scale-[0.98] motion-reduce:transition-none',
                isSelected ? 'skin-primary' : 'skin-raised',
            )}
        >
            <span
                aria-hidden
                className={cn(
                    'flex size-11 shrink-0 items-center justify-center rounded-full',
                    isSelected
                        ? 'bg-primary-foreground/15'
                        : 'bg-brand-soft text-brand-text',
                )}
            >
                <Zap className="size-5" />
            </span>
            <span className="flex min-w-0 flex-1 flex-col">
                <span
                    className={cn(
                        'text-sm font-medium',
                        !isSelected && 'text-muted-foreground',
                    )}
                >
                    Próximo horario libre
                </span>
                <span className="text-xl leading-tight font-extrabold tabular-nums">
                    {time}
                    {slot.promotion && (
                        <span
                            className={cn(
                                'ml-2 text-xs font-semibold',
                                !isSelected && 'text-brand-text',
                            )}
                        >
                            con promo
                        </span>
                    )}
                </span>
            </span>
            <span
                aria-hidden
                className={cn(
                    'inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full px-4 text-sm font-semibold',
                    isSelected
                        ? 'bg-primary-foreground/15'
                        : 'bg-brand-soft text-brand-text',
                )}
            >
                {isSelected ? (
                    <>
                        <Check className="size-4" /> Elegido
                    </>
                ) : (
                    <>
                        Elegir <ArrowRight className="size-4" />
                    </>
                )}
            </span>
        </button>
    );
}
