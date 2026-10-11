import { Printer, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCurrentSalon } from '@/hooks/use-current-salon';

const STEPS = [
    'Imprime la hoja',
    'La persona la lee, escribe su documento y la firma',
    'Guarda la hoja en el salón y márcalo aquí',
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
                    autorización escrita de la persona: su firma en la hoja o su
                    autorización desde su cuenta. Con decirlo no alcanza.
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
            <PrintConsentButton label="Imprimir la hoja" />
            <p className="text-sm text-muted-foreground">
                También puedes guardarla como PDF para imprimirla después.
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
                ¿Sin impresora ahora? Pídele que lo autorice desde su cuenta
                (Mis datos). No anotes alergias en otro campo: tenlas presentes
                hoy.
            </p>
        </div>
    );
}

type SheetProps = {
    clientId: number;
    clientName: string;
    text: string;
    /** Which wording this is, to match the paper with the record. */
    version: string;
};

/**
 * Only on paper: the authorization, ready to sign. `report-sheet` makes it
 * print on white with the light tokens whatever the mode on screen.
 */
export function CareConsentSheet({
    clientId,
    clientName,
    text,
    version,
}: SheetProps) {
    const { currentSalon } = useCurrentSalon();

    return (
        <article className="report-sheet hidden flex-col gap-8 text-foreground print:flex">
            <header className="border-b pb-4">
                <p className="text-sm text-muted-foreground">
                    {currentSalon?.name}
                </p>
                <p className="text-2xl font-semibold">
                    Autorización para guardar datos de salud: alergias y
                    reacciones a productos
                </p>
            </header>
            <div className="flex flex-col gap-3 text-base">
                <p>
                    Nombre: <strong>{clientName}</strong>
                </p>
                <p>Documento (DNI o CE): ______________________</p>
            </div>
            <p className="text-base leading-relaxed">{text}</p>
            <p className="text-sm leading-relaxed">
                Para ejercer mis derechos sobre estos datos puedo pedirlo en el
                salón. También puedo reclamar ante la Autoridad Nacional de
                Protección de Datos Personales.
            </p>
            <dl className="mt-16 grid grid-cols-2 gap-12 text-sm">
                <div className="border-t border-foreground pt-2">
                    <dt>Firma</dt>
                </div>
                <div className="border-t border-foreground pt-2">
                    <dt>Fecha</dt>
                </div>
            </dl>
            <p className="text-xs text-muted-foreground tabular-nums">
                Texto v{version} · Ficha n.º {clientId}
            </p>
        </article>
    );
}
