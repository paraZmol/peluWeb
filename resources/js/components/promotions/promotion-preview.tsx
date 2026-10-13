import { Eye, Store } from 'lucide-react';
import { BrandTheme } from '@/components/salon/brand-theme';
import { useCurrentSalon } from '@/hooks/use-current-salon';
import { formatCents } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PreviewLine, PromotionPreview } from '@/lib/promotion-preview';

/** More prices than this would bury the card: the rest are counted. */
const LINES_SHOWN = 3;

function Price({ line }: { line: PreviewLine }) {
    const hasPromo = line.promoCents !== line.normalCents;

    return (
        <li className="flex items-baseline justify-between gap-3 py-2">
            <span className="min-w-0 text-sm break-words">{line.name}</span>
            <span className="flex shrink-0 items-baseline gap-2 tabular-nums">
                {hasPromo && (
                    <s className="text-sm text-muted-foreground">
                        <span className="sr-only">Precio normal </span>
                        {formatCents(line.normalCents)}
                    </s>
                )}
                <span
                    className={cn(
                        'font-semibold',
                        hasPromo && 'text-brand-text',
                    )}
                >
                    {hasPromo && (
                        <span className="sr-only">Con promoción </span>
                    )}
                    {line.isGift ? 'Gratis' : formatCents(line.promoCents)}
                </span>
            </span>
        </li>
    );
}

type Props = {
    /** Null until the form says enough to write the card. */
    preview: PromotionPreview | null;
    /** The fine print of a saved promotion, as the server wrote it. */
    savedFinePrint?: string;
};

/**
 * The promotion as the client will read it, with its fine print, and what
 * it means for the salon. It changes as the form is filled in.
 */
export function PromotionPreviewPanel({ preview, savedFinePrint }: Props) {
    const { currentSalon } = useCurrentSalon();

    if (!preview) {
        return (
            <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                <Eye className="size-5" aria-hidden />
                Elige los servicios y verás aquí cómo la leerán tus clientes.
            </div>
        );
    }

    const extra = preview.lines.length - LINES_SHOWN;

    return (
        <section
            aria-label="Vista previa"
            aria-live="polite"
            className="flex flex-col gap-3"
        >
            <p className="flex items-center gap-2 text-xs font-bold tracking-[.16em] text-muted-foreground uppercase">
                <Eye className="size-3.5" aria-hidden /> Así la verán tus
                clientes
            </p>
            <BrandTheme brand={currentSalon?.brand ?? null}>
                <div className="rounded-xl p-4 skin-raised">
                    {preview.overline && (
                        <p className="text-xs font-bold tracking-[.16em] text-brand-text uppercase">
                            {preview.overline}
                        </p>
                    )}
                    <p className="display-type text-title-3 break-words">
                        {preview.title}
                    </p>
                    <ul className="mt-2 divide-y">
                        {preview.lines.slice(0, LINES_SHOWN).map((line) => (
                            <Price key={line.name} line={line} />
                        ))}
                    </ul>
                    {extra > 0 && (
                        <p className="text-sm text-muted-foreground">
                            y {extra} {extra === 1 ? 'servicio' : 'servicios'}{' '}
                            más
                        </p>
                    )}
                    <p className="mt-3 border-t pt-3 text-xs leading-relaxed text-muted-foreground">
                        {savedFinePrint ?? preview.finePrint}
                    </p>
                </div>
            </BrandTheme>

            <div className="rounded-xl bg-brand-soft p-4 text-sm">
                <p className="mb-1 flex items-center gap-2 font-medium">
                    <Store className="size-4" aria-hidden /> Para tu salón
                </p>
                {preview.summary.map((sentence) => (
                    <p key={sentence}>{sentence}</p>
                ))}
            </div>
        </section>
    );
}
