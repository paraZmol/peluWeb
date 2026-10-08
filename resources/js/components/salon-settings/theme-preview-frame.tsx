import { RotateCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { BrandTheme } from '@/components/salon/brand-theme';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { BrandTokens } from '@/types';
import type { ThemeChoice } from './types';

/** After this long without a response, offer to retry. */
const SLOW_AFTER_MS = 8000;

type Status = 'loading' | 'slow' | 'ready';

type Props = {
    choice: ThemeChoice;
    brand: BrandTokens | null;
};

/**
 * The real public page with this template, in a phone. The page renders at
 * 390 px (a phone's width) and is scaled to 75 % so the whole phone fits a
 * laptop screen. Only same-origin framing of the preview is allowed by the
 * server (SecurityHeaders::FRAMEABLE_BY_SELF).
 *
 * The bezel has no notch: it would cover the top of a real page. The frame
 * is out of the tab order so the dialog's focus stays on its own controls
 * (keys typed inside a frame never reach the dialog, Escape included);
 * "Abrir la página completa" is the way in with a keyboard.
 */
export function ThemePreviewFrame({ choice, brand }: Props) {
    const [status, setStatus] = useState<Status>('loading');
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        if (status !== 'loading') {
            return;
        }

        const timer = window.setTimeout(() => setStatus('slow'), SLOW_AFTER_MS);

        return () => window.clearTimeout(timer);
    }, [status, attempt]);

    const retry = () => {
        setStatus('loading');
        setAttempt((count) => count + 1);
    };

    return (
        <div className="mx-auto w-[calc(390px*0.75+1.25rem)] rounded-[2.5rem] bg-veil p-2.5 shadow-float ring-1 ring-border-strong">
            <div className="relative aspect-[9/19] overflow-hidden rounded-[2rem] bg-background">
                <iframe
                    key={attempt}
                    src={choice.previewUrl}
                    title={`Vista previa de la plantilla ${choice.label}`}
                    tabIndex={-1}
                    onLoad={() => setStatus('ready')}
                    className="absolute top-0 left-0 h-[calc(390px*19/9)] w-[390px] origin-top-left scale-75"
                />

                {status !== 'ready' && (
                    <BrandTheme
                        theme={choice.value}
                        brand={brand}
                        className="absolute inset-0 z-20 flex flex-col gap-4 bg-background p-4"
                    >
                        <Skeleton className="h-1/2 w-full rounded-2xl bg-muted" />
                        <Skeleton className="h-4 w-1/3 bg-muted" />
                        <Skeleton className="h-14 w-full rounded-xl bg-muted" />
                        <Skeleton className="h-14 w-full rounded-xl bg-muted" />
                        <div
                            role="status"
                            className="mt-auto flex flex-col items-center gap-2 pb-2 text-center text-sm text-muted-foreground"
                        >
                            {status === 'loading' ? (
                                'Cargando vista previa…'
                            ) : (
                                <>
                                    Está tardando más de lo normal.
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="rounded-full"
                                        onClick={retry}
                                    >
                                        <RotateCw aria-hidden />
                                        Reintentar
                                    </Button>
                                </>
                            )}
                        </div>
                    </BrandTheme>
                )}
            </div>
        </div>
    );
}
