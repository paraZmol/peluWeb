import { formatBalance } from '@/lib/cash';
import { formatCents } from '@/lib/format';
import { bucketName } from '@/lib/reports';
import type { Bucket, Grain } from '@/lib/reports';

const COLUMN: Record<Grain, string> = {
    day: 'Día',
    week: 'Semana',
    month: 'Mes',
};

type Props = { buckets: Bucket[]; grain: Grain };

/**
 * The chart's numbers as a table: for screen readers, for the keyboard and
 * for whoever wants the exact amounts.
 */
export function ChartTable({ buckets, grain }: Props) {
    return (
        <details className="group text-sm print:hidden">
            <summary className="flex min-h-11 cursor-pointer items-center rounded-md font-medium text-brand-text underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50">
                <span className="group-open:hidden">
                    Ver los valores en una tabla
                </span>
                <span className="hidden group-open:inline">
                    Ocultar la tabla
                </span>
            </summary>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[20rem] tabular-nums">
                    <caption className="sr-only">
                        Ingresos, gastos y resultado por{' '}
                        {COLUMN[grain].toLowerCase()}
                    </caption>
                    <thead>
                        <tr className="border-b text-muted-foreground">
                            <th
                                scope="col"
                                className="py-2 text-left font-medium"
                            >
                                {COLUMN[grain]}
                            </th>
                            <th
                                scope="col"
                                className="py-2 pl-3 text-right font-medium"
                            >
                                Ingresos
                            </th>
                            <th
                                scope="col"
                                className="py-2 pl-3 text-right font-medium"
                            >
                                Gastos
                            </th>
                            <th
                                scope="col"
                                className="py-2 pl-3 text-right font-medium"
                            >
                                Resultado
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {buckets.map((bucket) => (
                            <tr key={bucket.from}>
                                <th
                                    scope="row"
                                    className="py-2 text-left font-normal"
                                >
                                    {bucketName(bucket, grain)}
                                </th>
                                <td className="py-2 pl-3 text-right">
                                    {formatCents(bucket.incomeCents)}
                                </td>
                                <td className="py-2 pl-3 text-right">
                                    {formatCents(bucket.expensesCents)}
                                </td>
                                <td className="py-2 pl-3 text-right whitespace-nowrap">
                                    {formatBalance(
                                        bucket.incomeCents -
                                            bucket.expensesCents,
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </details>
    );
}
