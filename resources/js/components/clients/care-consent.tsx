import { Printer, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCurrentSalon } from '@/hooks/use-current-salon';

/** Why the field is locked, what the person signs and how to get it on paper. */
export function CareConsentNotice({ text }: { text: string }) {
    return (
        <div className="flex flex-col gap-3">
            <p className="flex items-start gap-2 rounded-lg bg-warning/10 p-3 text-sm">
                <ShieldAlert
                    className="mt-0.5 size-4 shrink-0 text-warning"
                    aria-hidden
                />
                <span>
                    Las alergias y otros datos de salud solo se guardan con la
                    autorización escrita de la persona. Que lo diga de palabra
                    no basta.
                </span>
            </p>
            <figure className="flex flex-col gap-3 rounded-lg border bg-surface-2 p-4">
                <figcaption className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Texto que firma la persona
                </figcaption>
                <blockquote className="text-sm leading-relaxed">
                    {text}
                </blockquote>
                <Button
                    type="button"
                    variant="outline"
                    className="sm:self-start"
                    onClick={() => window.print()}
                >
                    <Printer aria-hidden />
                    Imprimir autorización
                </Button>
            </figure>
        </div>
    );
}

type SheetProps = { clientName: string; text: string };

/**
 * Only on paper: the authorization, ready to sign. `report-sheet` makes it
 * print on white with the light tokens whatever the mode on screen.
 */
export function CareConsentSheet({ clientName, text }: SheetProps) {
    const { currentSalon } = useCurrentSalon();

    return (
        <article className="report-sheet hidden flex-col gap-8 text-foreground print:flex">
            <header className="border-b pb-4">
                <p className="text-sm text-muted-foreground">
                    {currentSalon?.name}
                </p>
                <p className="text-2xl font-semibold">
                    Autorización para guardar alergias y datos de cuidado
                </p>
            </header>
            <p className="text-base">
                Nombre: <strong>{clientName}</strong>
            </p>
            <p className="text-base leading-relaxed">{text}</p>
            <dl className="mt-16 grid grid-cols-2 gap-12 text-sm">
                <div className="border-t border-foreground pt-2">
                    <dt>Firma</dt>
                </div>
                <div className="border-t border-foreground pt-2">
                    <dt>Fecha</dt>
                </div>
            </dl>
        </article>
    );
}
