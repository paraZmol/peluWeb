import { Link } from '@inertiajs/react';
import { ArrowLeft, Eye } from 'lucide-react';
import { SALON_THEMES } from '@/components/salon/salon-themes';
import { edit } from '@/routes/salon';
import type { SalonTheme } from '@/types';

type Props = {
    theme: SalonTheme;
    usesSamplePhotos: boolean;
    /** Inside the phone of "Mi salón": there is nowhere to go back to. */
    isFramed: boolean;
};

/** Only for whoever manages the salon, while trying a template (`?diseno=`). */
export function PreviewBanner({ theme, usesSamplePhotos, isFramed }: Props) {
    return (
        <div
            role="status"
            className="sticky top-0 z-40 bg-foreground text-background shadow-float"
        >
            <div className="mx-auto flex w-full max-w-6xl items-center gap-3 px-4 py-2.5 text-sm">
                <Eye className="size-5 shrink-0" aria-hidden />
                <p className="min-w-0 flex-1 leading-snug">
                    <strong className="font-semibold">
                        Vista previa de la plantilla {SALON_THEMES[theme].label}
                    </strong>
                    {usesSamplePhotos && (
                        <span className="block text-background/75 sm:inline">
                            <span className="hidden sm:inline"> · </span>
                            Las fotos son de muestra: se reemplazan por las
                            tuyas
                        </span>
                    )}
                </p>
                {!isFramed && (
                    <Link
                        href={edit()}
                        aria-label="Volver a Mi salón"
                        className="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full border border-background/30 px-3.5 font-semibold transition-colors hover:bg-background/10"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                        <span className="hidden sm:inline">
                            Volver a Mi salón
                        </span>
                        <span className="sm:hidden">Mi salón</span>
                    </Link>
                )}
            </div>
        </div>
    );
}
