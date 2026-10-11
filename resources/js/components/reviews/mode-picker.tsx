import { router } from '@inertiajs/react';
import { Check, EyeOff, Globe, Lock } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';
import { update } from '@/routes/reviews/mode';
import { PublicModeDialog } from './public-mode-dialog';
import type { ReviewMode, ReviewModeOption } from './types';

const ICONS: Record<ReviewMode, LucideIcon> = {
    hidden: EyeOff,
    private: Lock,
    public: Globe,
};

type Props = { mode: ReviewMode; modes: ReviewModeOption[] };

/**
 * Who reads the salon's reviews: three large options that save on choosing.
 * Going public shows every review, so that one asks first.
 */
export function ModePicker({ mode, modes }: Props) {
    const [isConfirming, setIsConfirming] = useState(false);
    const [isSaving, setIsSaving] = useState(false);

    const save = (value: ReviewMode) =>
        router.put(
            update.url(),
            { mode: value },
            {
                preserveScroll: true,
                onStart: () => setIsSaving(true),
                onFinish: () => {
                    setIsSaving(false);
                    setIsConfirming(false);
                },
            },
        );

    const choose = (value: ReviewMode) => {
        if (value === 'public') {
            setIsConfirming(true);

            return;
        }

        save(value);
    };

    return (
        <fieldset disabled={isSaving} className="flex min-w-0 flex-col gap-3">
            <legend className="mb-3 text-base font-semibold">
                ¿Quién lee tus reseñas?
            </legend>
            <div className="grid gap-3 md:grid-cols-3">
                {modes.map((option) => {
                    const isChosen = option.value === mode;
                    const Icon = ICONS[option.value];

                    return (
                        <label
                            key={option.value}
                            className="relative flex cursor-pointer"
                        >
                            <input
                                type="radio"
                                name="review-mode"
                                value={option.value}
                                checked={isChosen}
                                onChange={() => choose(option.value)}
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
                })}
            </div>

            <PublicModeDialog
                open={isConfirming}
                isSaving={isSaving}
                onClose={() => setIsConfirming(false)}
                onConfirm={() => save('public')}
            />
        </fieldset>
    );
}
