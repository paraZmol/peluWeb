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

function Figure({ label, value, caption, className }: FigureProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-1 rounded-xl border bg-card p-4 shadow-card',
                className,
            )}
        >
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-2xl font-semibold tracking-tight tabular-nums">
                {value}
            </dd>
            {caption && (
                <dd className="text-sm text-muted-foreground tabular-nums">
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
                'grid grid-cols-2 gap-3',
                showsMoney ? 'lg:grid-cols-5' : 'lg:grid-cols-4',
            )}
        >
            <Figure
                label="Visitas"
                value={stats.visits}
                caption={
                    stats.firstVisit
                        ? `Desde ${formatLongDay(stats.firstVisit)}`
                        : 'Aún no ha venido'
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
                className={hasNoShows ? 'border-warning/50' : undefined}
                caption={
                    hasNoShows ? (
                        <span className="inline-flex items-center gap-1.5 text-warning">
                            <UserX className="size-4 shrink-0" aria-hidden />
                            Faltó {timesLabel(stats.noShows)}
                        </span>
                    ) : (
                        'Sin ausencias'
                    )
                }
            />
            <Figure label="Cancelaciones" value={stats.cancelled} />
            {stats.spent !== null && (
                <Figure
                    label="Gastado"
                    value={formatPrice(stats.spent)}
                    caption="En visitas completadas"
                    className="max-lg:col-span-2"
                />
            )}
        </dl>
    );
}
