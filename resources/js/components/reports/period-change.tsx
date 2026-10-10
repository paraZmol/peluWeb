import { ArrowDown, ArrowUp, Equal } from 'lucide-react';
import type { Period } from '@/components/commissions/types';
import { formatCents } from '@/lib/format';
import { compare, formatFullPeriod } from '@/lib/reports';

type Props = {
    currentCents: number;
    previousCents: number;
    /** The amount of the period before, in words ("Pérdida de S/ 20.00"). */
    previousText: string;
    previousPeriod: Period;
};

const signed = (percent: number) =>
    percent > 0 ? `+${percent} %` : `−${Math.abs(percent)} %`;

/**
 * How a figure moved against the period before. Told with an arrow and
 * words, never judged with color: more expenses is not good news.
 */
export function PeriodChange({
    currentCents,
    previousCents,
    previousText,
    previousPeriod,
}: Props) {
    const { deltaCents, percent } = compare(currentCents, previousCents);
    const Icon =
        deltaCents === 0 ? Equal : deltaCents > 0 ? ArrowUp : ArrowDown;

    return (
        <div className="flex flex-col gap-0.5 border-t pt-3 text-sm">
            {percent === null ? (
                <p className="text-muted-foreground">
                    Sin datos del período anterior
                </p>
            ) : (
                <p className="flex items-center gap-1 font-medium tabular-nums">
                    <Icon className="size-4 shrink-0" aria-hidden />
                    {deltaCents === 0
                        ? 'Igual que el período anterior'
                        : `${formatCents(Math.abs(deltaCents))} ${deltaCents > 0 ? 'más' : 'menos'} (${signed(percent)})`}
                </p>
            )}
            <p className="text-xs text-muted-foreground tabular-nums">
                {percent !== null && `${previousText} · `}
                {formatFullPeriod(previousPeriod.from, previousPeriod.to)}
            </p>
        </div>
    );
}
