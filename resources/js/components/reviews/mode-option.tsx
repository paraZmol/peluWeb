import { Check, EyeOff, Globe, Lock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ReviewMode, ReviewModeOption } from './types';

const ICONS: Record<ReviewMode, LucideIcon> = {
    hidden: EyeOff,
    private: Lock,
    public: Globe,
};

type Props = {
    option: ReviewModeOption;
    isChosen: boolean;
    onChoose: () => void;
};

/** One of the three ways to read reviews: a large radio with its hint. */
export function ModeOption({ option, isChosen, onChoose }: Props) {
    const Icon = ICONS[option.value];

    return (
        <label className="relative flex cursor-pointer">
            <input
                type="radio"
                name="review-mode"
                value={option.value}
                checked={isChosen}
                onChange={onChoose}
                aria-labelledby={`review-mode-${option.value}`}
                aria-describedby={`review-mode-${option.value}-hint`}
                className="peer absolute inset-0 z-10 cursor-pointer opacity-0"
            />
            <span
                className={cn(
                    'flex min-h-20 w-full items-start gap-3 rounded-xl border p-4 transition-colors peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50',
                    isChosen
                        ? 'border-primary bg-brand-soft shadow-card'
                        : 'bg-card peer-hover:border-border-strong',
                )}
            >
                <span
                    aria-hidden
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-full',
                        isChosen
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground',
                    )}
                >
                    <Icon className="size-5" />
                </span>
                <span className="flex min-w-0 flex-1 flex-col gap-1">
                    <span
                        id={`review-mode-${option.value}`}
                        className="font-semibold"
                    >
                        {option.label}
                    </span>
                    <span
                        id={`review-mode-${option.value}-hint`}
                        className="text-sm text-muted-foreground"
                    >
                        {option.hint}
                    </span>
                </span>
                {isChosen && (
                    <Check
                        className="size-5 shrink-0 text-brand-text"
                        aria-hidden
                    />
                )}
            </span>
        </label>
    );
}
