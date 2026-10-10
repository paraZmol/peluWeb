import { describeDifference, formatBalance, signedCents } from '@/lib/cash';
import { formatDateTime, formatPrice } from '@/lib/format';
import { CashDifference } from './cash-difference';
import { CashFigure } from './cash-figure';
import type { CashSession } from './types';

type Props = { history: CashSession[] };

/** The last closes, newest first: what was expected, counted and the gap. */
export function CashHistory({ history }: Props) {
    return (
        <section
            aria-labelledby="cash-history-title"
            className="flex flex-col gap-2"
        >
            <h2
                id="cash-history-title"
                className="px-1 text-sm font-medium text-muted-foreground"
            >
                Cierres anteriores
            </h2>
            {history.length === 0 ? (
                <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Aún no hay cierres. Cuando cierres la caja, aquí verás si
                    cuadró cada día.
                </p>
            ) : (
                <ul className="divide-y rounded-xl border bg-card">
                    {history.map((session) => (
                        <HistoryRow key={session.id} session={session} />
                    ))}
                </ul>
            )}
        </section>
    );
}

function HistoryRow({ session }: { session: CashSession }) {
    const closedAt = formatDateTime(session.closedAt ?? session.openedAt);
    const difference = describeDifference(session.differenceCents ?? 0, true);

    return (
        <li
            aria-label={`Cierre del ${closedAt}`}
            className="flex flex-col gap-2 p-4"
        >
            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-[minmax(0,1fr)_5.5rem_5.5rem_10.5rem] sm:items-center">
                <div className="col-span-2 min-w-0 sm:col-span-1">
                    <dt className="sr-only">Cierre</dt>
                    <dd className="font-medium">{closedAt}</dd>
                    <dd className="flex flex-wrap gap-x-1.5 text-sm text-muted-foreground">
                        <span className="whitespace-nowrap">
                            Abierta {formatDateTime(session.openedAt)}
                        </span>
                        <span className="whitespace-nowrap tabular-nums">
                            · fondo {formatPrice(session.openingCash)}
                        </span>
                    </dd>
                </div>
                <CashFigure label="Esperado">
                    {formatBalance(signedCents(session.expectedCash ?? '0'))}
                </CashFigure>
                <CashFigure label="Contado">
                    {formatPrice(session.countedCash ?? '0.00')}
                </CashFigure>
                <CashFigure
                    label="Diferencia"
                    className="col-span-2 sm:col-span-1"
                >
                    <CashDifference difference={difference} />
                </CashFigure>
            </dl>
            {session.note && (
                <p className="text-sm break-words text-muted-foreground">
                    Nota: {session.note}
                </p>
            )}
        </li>
    );
}
