import { LifeBuoy } from 'lucide-react';
import { formatDateTime, formatMinutes } from '@/lib/format';
import type { SupportVisitSummary } from './types';

type Props = { visits: SupportVisitSummary[] };

/**
 * Each time peluWeb support looked at the salon (read only), with why and
 * for how long: the owner can always see who came in.
 */
export function SupportVisits({ visits }: Props) {
    return (
        <section
            aria-labelledby="support-visits-title"
            className="flex flex-col gap-2"
        >
            <div className="px-1">
                <h2 id="support-visits-title" className="text-lg font-semibold">
                    Visitas de soporte
                </h2>
                <p className="text-sm text-muted-foreground">
                    Cuando el equipo de peluWeb entra a ayudarte, solo puede
                    mirar: no cambia ni descarga nada. Te avisamos por correo
                    cada vez.
                </p>
            </div>
            {visits.length === 0 ? (
                <div className="flex items-center gap-3 rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                    <LifeBuoy className="size-5 shrink-0" aria-hidden />
                    Nadie de soporte ha entrado a tu salón.
                </div>
            ) : (
                <ul className="divide-y rounded-xl border bg-card">
                    {visits.map((visit) => (
                        <li key={visit.id} className="flex flex-col gap-1 p-4">
                            <p className="text-sm font-medium">
                                {formatDateTime(visit.startedAt)}
                                <span className="font-normal text-muted-foreground">
                                    {' · '}
                                    {visit.minutes === null
                                        ? 'En curso'
                                        : formatMinutes(
                                              Math.max(visit.minutes, 1),
                                          )}
                                </span>
                            </p>
                            <p className="text-sm break-words text-muted-foreground">
                                Motivo: {visit.reason}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
