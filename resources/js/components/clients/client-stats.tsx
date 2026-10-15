import { UserX } from 'lucide-react';
import type { ReactNode } from 'react';
import { formatLongDay, timesLabel } from '@/lib/clients';
import { formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ClientStats as Stats } from './types';

type FigureProps = {
    label: string;
    value: ReactNode;
    caption?: ReactNode;
    className?: string;
};

/** A row of the one card on a phone; a card of its own from `lg` up. */
function Figure({ label, value, caption, className }: FigureProps) {
    return (
        <div
            className={cn(
                'grid grid-cols-[1fr_auto] items-baseline gap-x-3 px-4 py-2.5 lg:flex lg:flex-col lg:items-stretch lg:gap-1 lg:rounded-xl lg:border lg:bg-card lg:p-4 lg:shadow-card',
                className,
            )}
        >
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-base font-semibold tabular-nums lg:text-2xl lg:tracking-tight">
                {value}
            </dd>
            {caption && (
                <dd className="col-span-2 text-sm text-muted-foreground tabular-nums">
                    {caption}
                </dd>
            )}
        </div>
    );
}

/** How often they come, how reliable they are and, for who may, what they spent. */
export function ClientStats({ stats }: { stats: Stats }) {
    const showsMoney = stats.spent !== null;
    const hasNoShows = stats.noShows > 0;

    return (
        <dl
            aria-label="Resumen de visitas"
            className={cn(
                'max-lg:divide-y max-lg:rounded-xl max-lg:border max-lg:bg-card max-lg:shadow-card lg:grid lg:gap-3',
                showsMoney ? 'lg:grid-cols-5' : 'lg:grid-cols-4',
            )}
        >
            <Figure
                label="Visitas"
                value={stats.visits}
                caption={
                    // On a phone the header already says since when.
                    <span className="max-lg:hidden">
                        {stats.firstVisit
                            ? `Desde ${formatLongDay(stats.firstVisit)}`
                            : 'Aún no ha venido'}
                    </span>
                }
            />
            <Figure
                label="Última visita"
                value={
                    stats.lastVisit ? formatLongDay(stats.lastVisit) : 'Ninguna'
                }
            />
            <Figure
                label="Ausencias"
                value={stats.noShows}
                className={hasNoShows ? 'lg:border-warning/50' : undefined}
                caption={
                    hasNoShows ? (
                        <span className="inline-flex items-center gap-1.5 text-warning">
                            <UserX className="size-4 shrink-0" aria-hidden />
                            Faltó {timesLabel(stats.noShows)}
                        </span>
                    ) : (
                        <span className="max-lg:hidden">Sin ausencias</span>
                    )
                }
            />
            <Figure label="Cancelaciones" value={stats.cancelled} />
            {stats.spent !== null && (
                <Figure
                    label="Gastado"
                    value={formatPrice(stats.spent)}
                    caption={
                        <span className="max-lg:hidden">
                            En visitas completadas
                        </span>
                    }
                />
            )}
        </dl>
    );
}
