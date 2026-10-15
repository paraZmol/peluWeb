import type { ReactNode } from 'react';
import { StatusBadge } from '@/components/booking/status-badge';
import { formatVisitDay } from '@/lib/clients';
import { formatPrice, formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ClientVisit } from './types';

type Props = {
    /** Unique on the page: names the section for assistive technology. */
    id: string;
    title: string;
    visits: ClientVisit[];
    /** What to show when there is nothing: an icon, a sentence, an action. */
    empty: ReactNode;
    /** A closing line, e.g. that older visits are not listed. */
    footnote?: string;
    className?: string;
};

function VisitRow({ visit }: { visit: ClientVisit }) {
    return (
        <li className="flex flex-col gap-1 p-4">
            <div className="flex items-start justify-between gap-3">
                <p className="font-medium tabular-nums">
                    <span className="inline-block first-letter:uppercase">
                        {formatVisitDay(visit.date)}
                    </span>
                    <span className="font-normal text-muted-foreground">
                        {' · '}
                        {formatTime(visit.time)}
                    </span>
                </p>
                <StatusBadge
                    status={visit.status}
                    label={visit.statusLabel}
                    className="mt-0.5 shrink-0"
                />
            </div>
            <p className="break-words">{visit.services.join(', ')}</p>
            <p className="flex flex-wrap justify-between gap-x-3 text-sm text-muted-foreground">
                <span className="break-words">Con {visit.staffName}</span>
                {visit.charged !== null && (
                    <span className="whitespace-nowrap tabular-nums">
                        Cobrado {formatPrice(visit.charged)}
                    </span>
                )}
            </p>
            {visit.note && (
                <p className="mt-1 border-l-2 border-primary/40 pl-3 text-sm break-words italic">
                    "{visit.note}"
                </p>
            )}
        </li>
    );
}

/** A list of the client's visits (coming or past) with its empty state. */
export function VisitList({
    id,
    title,
    visits,
    empty,
    footnote,
    className,
}: Props) {
    return (
        <section
            aria-labelledby={id}
            className={cn('flex flex-col gap-3', className)}
        >
            <h2 id={id} className="text-lg font-semibold">
                {title}
            </h2>
            {visits.length === 0 ? (
                <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-6 text-center text-muted-foreground">
                    {empty}
                </div>
            ) : (
                <ul className="divide-y rounded-xl border bg-card shadow-card">
                    {visits.map((visit) => (
                        <VisitRow key={visit.id} visit={visit} />
                    ))}
                </ul>
            )}
            {footnote && (
                <p className="text-center text-sm text-muted-foreground">
                    {footnote}
                </p>
            )}
        </section>
    );
}
