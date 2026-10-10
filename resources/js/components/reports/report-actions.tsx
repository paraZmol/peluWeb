import { Link } from '@inertiajs/react';
import { ChartColumn, Download, Printer } from 'lucide-react';
import type { Period } from '@/components/commissions/types';
import { Button } from '@/components/ui/button';
import { useCurrentSalon } from '@/hooks/use-current-salon';
import { formatFullPeriod } from '@/lib/reports';
import { index as agenda } from '@/routes/agenda';
import { exportMethod } from '@/routes/reports';

/** Take the period away: its movements as a file, or the report on paper. */
export function ReportActions({ period }: { period: Period }) {
    return (
        <div className="flex flex-col gap-2 sm:flex-row print:hidden">
            <Button asChild variant="outline" className="h-11">
                {/* A file, not a page: a plain link, so Inertia stays out. */}
                <a
                    href={exportMethod.url({
                        query: { desde: period.from, hasta: period.to },
                    })}
                    download
                >
                    <Download aria-hidden />
                    Descargar movimientos (CSV)
                </a>
            </Button>
            <Button
                type="button"
                variant="outline"
                className="h-11"
                onClick={() => window.print()}
            >
                <Printer aria-hidden />
                Imprimir o guardar como PDF
            </Button>
        </div>
    );
}

/** Only on paper: whose report this is and which days it covers. */
export function PrintHeader({ period }: { period: Period }) {
    const { currentSalon } = useCurrentSalon();

    return (
        <header className="hidden border-b pb-4 print:block">
            <p className="text-2xl font-semibold">{currentSalon?.name}</p>
            <p className="text-base">Reporte de ganancias y pérdidas</p>
            <p className="text-sm text-muted-foreground tabular-nums">
                {formatFullPeriod(period.from, period.to)}
            </p>
        </header>
    );
}

/** A period in which nothing happened, with the way to change that. */
export function EmptyReport() {
    return (
        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center">
            <ChartColumn className="size-6 text-muted-foreground" aria-hidden />
            <p className="font-medium">No hubo movimientos en este período</p>
            <p className="max-w-md text-muted-foreground">
                Cuando completes y cobres citas o registres gastos en estas
                fechas, aquí verás cuánto entró, cuánto salió y qué quedó.
                Prueba con otro período o revisa la agenda.
            </p>
            <Button asChild className="h-11 print:hidden">
                <Link href={agenda()}>Ir a la agenda</Link>
            </Button>
        </div>
    );
}
