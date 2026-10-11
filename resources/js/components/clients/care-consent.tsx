import { Printer, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCurrentSalon } from '@/hooks/use-current-salon';

const STEPS = [
    'Imprime la hoja o guárdala como PDF',
    'La persona la firma',
    'Marca que el salón la guardó',
];

/** Prints the sheet to sign: the page's only content on paper. */
export function PrintConsentButton({ label }: { label: string }) {
    return (
        <Button
            type="button"
            variant="outline"
            className="sm:self-start"
            onClick={() => window.print()}
        >
            <Printer aria-hidden />
            {label}
        </Button>
    );
}

/** Why the field is locked and the three steps that open it. */
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
                    autorización escrita de la persona. Hace falta su firma en
                    papel; con decirlo no alcanza.
                </span>
            </p>
            <ol className="flex flex-col gap-2 text-sm">
                {STEPS.map((step, index) => (
                    <li key={step} className="flex items-center gap-3">
                        <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-semibold text-brand-text tabular-nums">
                            {index + 1}
                            <span className="sr-only">.</span>
                        </span>
                        {step}
                    </li>
                ))}
            </ol>
            <PrintConsentButton label="Imprimir o guardar como PDF" />
            <p className="text-sm text-muted-foreground">
                Ctrl+P en esta página imprime esta hoja, no la ficha.
            </p>
            <details className="rounded-lg border bg-surface-2 text-sm">
                <summary className="flex min-h-11 cursor-pointer items-center px-4 font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50">
                    Ver lo que firma la persona
                </summary>
                <blockquote className="px-4 pb-4 leading-relaxed">
                    {text}
                </blockquote>
            </details>
            <p className="text-sm text-muted-foreground">
                ¿Sin impresora ahora? No anotes alergias en otro campo: tenlas
                presentes hoy y regístralas cuando tengas la hoja firmada.
            </p>
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
