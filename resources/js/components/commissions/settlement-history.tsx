import { CashFigure } from '@/components/cash/cash-figure';
import { countVisits, formatPeriod } from '@/lib/commissions';
import { formatDate, formatPrice } from '@/lib/format';
import type { Settlement } from './types';

type Props = { history: Settlement[] };

/** The last payments, newest first: who, for which days and how much. */
export function SettlementHistory({ history }: Props) {
    return (
        <section
            aria-labelledby="settlements-title"
            className="flex flex-col gap-2"
        >
            <h2
                id="settlements-title"
                className="px-1 text-sm font-medium text-muted-foreground"
            >
                Liquidaciones anteriores
            </h2>
            {history.length === 0 ? (
                <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Aún no has liquidado comisiones. Cuando pagues un período,
                    aquí queda a quién, cuánto y qué día.
                </p>
            ) : (
                <ul className="divide-y rounded-xl border bg-card">
                    {history.map((settlement) => (
                        <li
                            key={settlement.id}
                            aria-label={`Liquidación de ${settlement.staffName}, ${formatPeriod(settlement)}`}
                            className="p-4"
                        >
                            <dl className="grid grid-cols-3 gap-x-4 gap-y-2 sm:grid-cols-[minmax(0,1fr)_5rem_6rem_6.5rem] sm:items-center">
                                <div className="col-span-3 min-w-0 sm:col-span-1">
                                    <dt className="sr-only">Profesional</dt>
                                    <dd className="font-medium break-words">
                                        {settlement.staffName}
                                    </dd>
                                    <dt className="sr-only">Período</dt>
                                    <dd className="text-sm text-muted-foreground">
                                        {formatPeriod(settlement)}
                                    </dd>
                                </div>
                                <CashFigure label="Citas">
                                    {countVisits(settlement.visits)}
                                </CashFigure>
                                <CashFigure label="Total">
                                    {formatPrice(settlement.total)}
                                </CashFigure>
                                <CashFigure label="Pagado">
                                    {formatDate(settlement.paidOn)}
                                </CashFigure>
                            </dl>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
