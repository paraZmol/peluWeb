import { Button } from '@/components/ui/button';
import { formatBalance, signedCents, splitMethods } from '@/lib/cash';
import { formatDateTime, formatPrice } from '@/lib/format';
import { CashDialog } from './cash-dialog';
import { CloseCashForm } from './close-cash-form';
import type { CashSession } from './types';

type Props = { session: CashSession };

/** The open till: since when, what it should hold and how that adds up. */
export function OpenCashCard({ session }: Props) {
    const { cash } = splitMethods(session.methods);
    const lines = [
        { label: 'Fondo inicial', amount: formatPrice(session.openingCash) },
        {
            label: 'Entró en efectivo',
            amount: `+ ${formatPrice(cash?.in ?? '0.00')}`,
        },
        {
            label: 'Salió en efectivo',
            amount: `− ${formatPrice(cash?.out ?? '0.00')}`,
        },
    ];

    return (
        <section
            aria-label="Estado de la caja"
            className="flex flex-col gap-5 rounded-xl border bg-card p-5 shadow-card"
        >
            <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <span className="inline-flex items-center gap-1.5 rounded-full bg-success/15 px-2.5 py-0.5 font-medium text-success">
                    <span
                        aria-hidden
                        className="size-1.5 rounded-full bg-success"
                    />
                    Caja abierta
                </span>
                <span className="text-muted-foreground">
                    desde {formatDateTime(session.openedAt)}
                </span>
            </p>

            <div>
                <p className="text-sm text-muted-foreground">
                    Efectivo esperado en caja
                </p>
                <p
                    data-testid="cash-expected"
                    className="text-4xl font-semibold tracking-tight tabular-nums"
                >
                    {formatBalance(signedCents(session.expectedCash ?? '0'))}
                </p>
            </div>

            <dl className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1.5 border-t pt-4 text-sm">
                {lines.map((line) => (
                    <div key={line.label} className="contents">
                        <dt className="text-muted-foreground">{line.label}</dt>
                        <dd className="text-right whitespace-nowrap tabular-nums">
                            {line.amount}
                        </dd>
                    </div>
                ))}
            </dl>

            <CashDialog
                trigger={<Button className="h-12 sm:h-10">Cerrar caja</Button>}
                title="Cerrar caja"
                description="Cuenta el efectivo de la caja. Al cerrar, el resultado queda guardado y no se puede cambiar."
            >
                <CloseCashForm session={session} />
            </CashDialog>
        </section>
    );
}
