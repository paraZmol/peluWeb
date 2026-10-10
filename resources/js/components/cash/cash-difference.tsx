import { Check, Plus, TriangleAlert } from 'lucide-react';
import type { CashDifference as Difference, CashTone } from '@/lib/cash';
import { cn } from '@/lib/utils';

const TONES: Record<CashTone, { icon: typeof Check; className: string }> = {
    even: { icon: Check, className: 'text-success' },
    short: { icon: TriangleAlert, className: 'text-warning' },
    over: { icon: Plus, className: 'text-foreground' },
};

type Props = { difference: Difference; className?: string };

/** The result of a count: an icon and words, so color is never the only cue. */
export function CashDifference({ difference, className }: Props) {
    const { icon: Icon, className: tone } = TONES[difference.tone];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 font-medium whitespace-nowrap tabular-nums',
                tone,
                className,
            )}
        >
            <Icon className="size-4 shrink-0" aria-hidden />
            {difference.label}
        </span>
    );
}
