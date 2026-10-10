import { breakdownByCategory } from '@/lib/expenses';
import { formatCents, formatMonth, formatPrice } from '@/lib/format';
import type { Expense } from './types';

type Props = {
    month: string;
    /** The month's total in soles, as the server added it. */
    total: string;
    expenses: Expense[];
};

/** The month at a glance: how much went out and on what. */
export function MonthSummary({ month, total, expenses }: Props) {
    const shares = breakdownByCategory(expenses);

    return (
        <section
            aria-label="Resumen del mes"
            className="flex flex-col gap-5 rounded-xl border bg-card p-5 shadow-card"
        >
            <div>
                <p className="text-sm text-muted-foreground">
                    Gastos de {formatMonth(month).toLowerCase()}
                </p>
                <p
                    data-testid="expenses-total"
                    className="text-4xl font-semibold tracking-tight tabular-nums"
                >
                    {formatPrice(total)}
                </p>
                <p className="text-sm text-muted-foreground tabular-nums">
                    {expenses.length === 1
                        ? '1 gasto'
                        : `${expenses.length} gastos`}
                </p>
            </div>

            {shares.length > 0 && (
                <div className="flex flex-col gap-3 border-t pt-4">
                    <h2 className="text-sm font-medium">Por categoría</h2>
                    <ul className="flex flex-col gap-3">
                        {shares.map((share) => (
                            <li key={share.key} className="flex flex-col gap-1">
                                <div className="flex items-baseline justify-between gap-3 text-sm">
                                    <span className="min-w-0 break-words">
                                        {share.name}
                                    </span>
                                    <span className="shrink-0 tabular-nums">
                                        <span className="font-medium">
                                            {formatCents(share.cents)}
                                        </span>
                                        <span className="ml-2 inline-block w-12 text-right whitespace-nowrap text-muted-foreground">
                                            {share.percent} %
                                        </span>
                                    </span>
                                </div>
                                <div
                                    aria-hidden
                                    className="h-2 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        className="h-full rounded-full bg-primary"
                                        style={{
                                            width: `${Math.max(share.percent, 1)}%`,
                                        }}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </section>
    );
}
