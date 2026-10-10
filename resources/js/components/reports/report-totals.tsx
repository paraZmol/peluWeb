import { TrendingDown, TrendingUp } from 'lucide-react';
import type { ReactNode } from 'react';
import type { Period } from '@/components/commissions/types';
import { countVisits } from '@/lib/commissions';
import { formatCents } from '@/lib/format';
import { cn } from '@/lib/utils';
import { PeriodChange } from './period-change';
import type { ReportTotals as Totals } from './types';

type Props = { totals: Totals; previous: Totals; previousPeriod: Period };

/** "Ganancia de S/ 80.00" or "Pérdida de S/ 20.00": the sign, in words. */
const profitText = (cents: number) =>
    `${cents < 0 ? 'Pérdida' : 'Ganancia'} de ${formatCents(Math.abs(cents))}`;

/** The period in three figures, each against the period before. */
export function ReportTotals({ totals, previous, previousPeriod }: Props) {
    const isLoss = totals.profitCents < 0;
    const ProfitIcon = isLoss ? TrendingDown : TrendingUp;

    return (
        <section
            aria-label="Resumen del período"
            className="grid gap-4 sm:grid-cols-3"
        >
            <Figure
                label="Ingresos"
                testId="report-income"
                cents={totals.incomeCents}
                note={`${countVisits(totals.visits)} ${totals.visits === 1 ? 'completada' : 'completadas'}`}
            >
                <PeriodChange
                    currentCents={totals.incomeCents}
                    previousCents={previous.incomeCents}
                    previousText={formatCents(previous.incomeCents)}
                    previousPeriod={previousPeriod}
                />
            </Figure>

            <Figure
                label="Gastos"
                testId="report-expenses"
                cents={totals.expensesCents}
                note="Comisiones pagadas incluidas"
            >
                <PeriodChange
                    currentCents={totals.expensesCents}
                    previousCents={previous.expensesCents}
                    previousText={formatCents(previous.expensesCents)}
                    previousPeriod={previousPeriod}
                />
            </Figure>

            <Figure
                label={
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 font-medium',
                            isLoss && 'text-destructive-foreground',
                            totals.profitCents > 0 && 'text-success',
                        )}
                    >
                        <ProfitIcon className="size-4 shrink-0" aria-hidden />
                        {isLoss ? 'Pérdida' : 'Ganancia'}
                    </span>
                }
                testId="report-profit"
                cents={Math.abs(totals.profitCents)}
                note="Ingresos menos gastos"
                isHighlighted
            >
                <PeriodChange
                    currentCents={totals.profitCents}
                    previousCents={previous.profitCents}
                    previousText={profitText(previous.profitCents)}
                    previousPeriod={previousPeriod}
                />
            </Figure>
        </section>
    );
}

type FigureProps = {
    label: ReactNode;
    testId: string;
    cents: number;
    note: string;
    isHighlighted?: boolean;
    children: ReactNode;
};

function Figure({
    label,
    testId,
    cents,
    note,
    isHighlighted = false,
    children,
}: FigureProps) {
    return (
        <div
            className={cn(
                'flex min-w-0 break-inside-avoid flex-col gap-3 rounded-xl border bg-card p-5 shadow-card',
                isHighlighted && 'border-brand-line',
            )}
        >
            <div>
                <p className="text-sm text-muted-foreground">{label}</p>
                <p
                    data-testid={testId}
                    className="text-3xl font-semibold tracking-tight break-words tabular-nums lg:text-4xl"
                >
                    {formatCents(cents)}
                </p>
                <p className="text-sm text-muted-foreground">{note}</p>
            </div>
            {children}
        </div>
    );
}
