import { ImageDown, LoaderCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { useSalonTheme } from '@/components/salon/brand-theme';
import { templateProps } from '@/components/salon/portal-theme';
import { PLATFORM_THEME } from '@/components/salon/salon-themes';
import { Button } from '@/components/ui/button';
import { posterBlob, saveFile } from '@/lib/poster-look';
import { isIos } from '@/lib/pwa';
import { posterContent, posterFileName } from '@/lib/promo-poster';
import { savedPromotionFields } from '@/lib/promotion-cards';
import { previewOf } from '@/lib/promotion-preview';
import type { SalonTheme } from '@/types';
import type { Promotion, PromotionContext } from './types';

/** iPhone and iPad may open the image in a new tab instead of saving it. */
function readyMessage(): string {
    return isIos(navigator.userAgent, navigator.maxTouchPoints)
        ? 'Imagen lista. Si se abrió en otra pestaña, mantén pulsada la imagen y elige «Guardar».'
        : 'Imagen lista.';
}

type Props = {
    promotion: Promotion;
    context: PromotionContext;
    /** The salon's template; without it, the platform's with its color. */
    theme?: SalonTheme;
};

/**
 * "Descargar imagen": a 1080 × 1350 PNG to post on Instagram, drawn in the
 * browser with the salon's colors and fonts. It says how long it runs, its
 * places and where the conditions are, like every piece that is shared.
 */
export function PosterButton({ promotion, context, theme }: Props) {
    const { brand } = useSalonTheme();
    const probe = useRef<HTMLSpanElement>(null);
    const [state, setState] = useState<'idle' | 'working' | 'ready' | 'failed'>(
        'idle',
    );

    const download = async () => {
        if (!probe.current) {
            return;
        }

        setState('working');

        try {
            const content = posterContent({
                promotion,
                preview: previewOf(
                    savedPromotionFields(promotion, promotion.name),
                    context,
                ),
                services: context.services,
                staffNames: context.team
                    .filter((person) =>
                        promotion.form.staff?.includes(person.id),
                    )
                    .map((person) => person.name),
                salonName: context.salonName,
            });
            saveFile(
                await posterBlob(probe.current, content),
                posterFileName(promotion),
            );
            setState('ready');
        } catch {
            setState('failed');
        }
    };

    return (
        <>
            <span {...templateProps(theme ?? PLATFORM_THEME, brand)} hidden>
                <span ref={probe} />
            </span>
            <Button
                type="button"
                variant="outline"
                disabled={state === 'working'}
                onClick={download}
            >
                {state === 'working' ? (
                    <LoaderCircle className="animate-spin" aria-hidden />
                ) : (
                    <ImageDown aria-hidden />
                )}
                {state === 'working'
                    ? 'Preparando imagen…'
                    : 'Descargar imagen'}
            </Button>
            {state === 'ready' && (
                <p role="status" className="text-sm text-muted-foreground">
                    {readyMessage()}
                </p>
            )}
            {state === 'failed' && (
                <p role="alert" className="text-sm text-destructive-foreground">
                    No se pudo crear la imagen. Inténtalo de nuevo.
                </p>
            )}
        </>
    );
}
