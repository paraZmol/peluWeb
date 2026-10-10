import { formatBalance } from '@/lib/cash';
import { formatCents } from '@/lib/format';
import { bucketName } from '@/lib/reports';
import type { Bucket, Grain } from '@/lib/reports';

type Props = { bucket: Bucket | null; grain: Grain };

/**
 * The amounts of the bar under the finger or the cursor. It keeps its room
 * while nothing is chosen, so the chart below does not jump.
 */
export function ChartReadout({ bucket, grain }: Props) {
    if (!bucket) {
        return (
            <p className="min-h-10 text-sm text-muted-foreground print:hidden">
                Toca una barra o pasa el cursor para ver sus valores.
            </p>
        );
    }

    const figures = [
        `Ingresos ${formatCents(bucket.incomeCents)}`,
        `Gastos ${formatCents(bucket.expensesCents)}`,
        `Resultado ${formatBalance(bucket.incomeCents - bucket.expensesCents)}`,
    ];

    return (
        <p className="flex min-h-10 flex-wrap content-start gap-x-3 text-sm tabular-nums print:hidden">
            <span className="font-medium whitespace-nowrap">
                {bucketName(bucket, grain)}
            </span>
            {figures.map((figure) => (
                <span key={figure} className="whitespace-nowrap">
                    {figure}
                </span>
            ))}
        </p>
    );
}
