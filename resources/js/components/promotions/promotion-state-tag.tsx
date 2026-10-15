import {
    CalendarClock,
    CircleCheck,
    CircleSlash,
    FilePen,
    Pause,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { PromotionState } from './types';

type Look = {
    icon: LucideIcon;
    style: string;
    /** Success and warning are too light for words: only the icon wears them. */
    iconStyle?: string;
};

const LOOKS: Record<PromotionState, Look> = {
    draft: { icon: FilePen, style: 'bg-muted text-muted-foreground' },
    scheduled: {
        icon: CalendarClock,
        style: 'bg-brand-soft text-brand-text',
    },
    active: {
        icon: CircleCheck,
        style: 'bg-success/15 text-foreground',
        iconStyle: 'text-success',
    },
    paused: { icon: Pause, style: 'bg-muted text-foreground' },
    ended: {
        icon: CircleSlash,
        style: 'border border-border text-muted-foreground',
    },
    needs_review: {
        icon: TriangleAlert,
        style: 'bg-warning/15 text-foreground',
        iconStyle: 'text-warning',
    },
};

type Props = { state: PromotionState; label: string };

/** Where a promotion stands. The label always says it; color only reinforces it. */
export function PromotionStateTag({ state, label }: Props) {
    const { icon: Icon, style, iconStyle } = LOOKS[state];

    return (
        <span
            data-state={state}
            className={cn(
                'inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                style,
            )}
        >
            <Icon className={cn('size-3.5', iconStyle)} aria-hidden />
            {label}
        </span>
    );
}
