import { router } from '@inertiajs/react';
import { useState } from 'react';
import { update } from '@/routes/reviews/mode';
import { ModeOption } from './mode-option';
import { NetworkProblem } from './network-problem';
import { PublicModeDialog } from './public-mode-dialog';
import type { ReviewMode, ReviewModeOption } from './types';

type Props = { mode: ReviewMode; modes: ReviewModeOption[] };

/** Nothing in flight, on its way, kept, or lost before reaching the server. */
type SaveState = 'idle' | 'saving' | 'saved' | 'failed';

/**
 * Who reads the salon's reviews: three large options that save on choosing.
 * Going public shows every review, so that one asks first. The options are
 * never switched off while saving (the focus would be lost): a second choice
 * is ignored until the first one lands.
 */
export function ModePicker({ mode, modes }: Props) {
    const [isConfirming, setIsConfirming] = useState(false);
    const [saveState, setSaveState] = useState<SaveState>('idle');
    const isSaving = saveState === 'saving';

    const save = (value: ReviewMode) => {
        if (isSaving) {
            return;
        }

        router.put(
            update.url(),
            { mode: value },
            {
                preserveScroll: true,
                onStart: () => setSaveState('saving'),
                onSuccess: () => setSaveState('saved'),
                onError: () => setSaveState('idle'),
                onNetworkError: () => setSaveState('failed'),
                onFinish: () => setIsConfirming(false),
            },
        );
    };

    const choose = (value: ReviewMode) => {
        if (isSaving || value === mode) {
            return;
        }

        if (value === 'public') {
            setIsConfirming(true);

            return;
        }

        save(value);
    };

    return (
        <fieldset aria-busy={isSaving} className="flex min-w-0 flex-col gap-3">
            <legend className="mb-3 text-base font-semibold">
                ¿Quién lee tus reseñas?
            </legend>
            <div className="grid gap-3 md:grid-cols-3">
                {modes.map((option) => (
                    <ModeOption
                        key={option.value}
                        option={option}
                        isChosen={option.value === mode}
                        onChoose={() => choose(option.value)}
                    />
                ))}
            </div>

            <p aria-live="polite" className="sr-only">
                {saveState === 'saved' && 'Guardado'}
            </p>
            <NetworkProblem isShown={saveState === 'failed'} />

            <PublicModeDialog
                open={isConfirming}
                isSaving={isSaving}
                onClose={() => setIsConfirming(false)}
                onConfirm={() => save('public')}
            />
        </fieldset>
    );
}
