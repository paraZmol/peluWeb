import { formatBalance, splitMethods } from '@/lib/cash';
import { formatPrice } from '@/lib/format';
import { CashFigure } from './cash-figure';
import type { CashMethod } from './types';

type Props = { methods: CashMethod[] };

/** What came in and went out by each method since the till was opened. */
export function MethodList({ methods }: Props) {
    const { cash, others } = splitMethods(methods);

    return (
        <section
            aria-labelledby="cash-methods-title"
            className="flex flex-col gap-2"
        >
            <h2
                id="cash-methods-title"
                className="px-1 text-sm font-medium text-muted-foreground"
            >
                Movimientos desde la apertura
            </h2>
            {cash && (
                <ul className="rounded-xl border bg-card">
                    <MethodRow method={cash} />
                </ul>
            )}
            {others.length > 0 && (
                <>
                    <p className="px-1 pt-2 text-sm text-muted-foreground">
                        Lo que no es efectivo no está en la caja: compáralo con
                        tu app de Yape/Plin o tu banco.
                    </p>
                    <ul className="divide-y rounded-xl border bg-card">
                        {others.map((method) => (
                            <MethodRow key={method.value} method={method} />
                        ))}
                    </ul>
                </>
            )}
        </section>
    );
}

function MethodRow({ method }: { method: CashMethod }) {
    return (
        <li
            aria-label={method.label}
            className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
        >
            <p className="min-w-0 font-medium break-words">{method.label}</p>
            <dl className="grid grid-cols-3 gap-3 sm:w-[22rem] sm:shrink-0">
                <CashFigure label="Entró">{formatPrice(method.in)}</CashFigure>
                <CashFigure label="Salió">{formatPrice(method.out)}</CashFigure>
                <CashFigure label="Neto">
                    {formatBalance(method.netCents)}
                </CashFigure>
            </dl>
        </li>
    );
}
