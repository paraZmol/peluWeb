import { countVisits } from '@/lib/commissions';
import { formatCents } from '@/lib/format';
import { shareOf } from '@/lib/reports';
import { ReportSection, SectionEmpty } from './report-section';
import type { ClientMix as Mix, LostVisits as Lost } from './types';

/** Visits that did not happen and what they would have brought in. */
export function LostVisits({ lost }: { lost: Lost }) {
    const hasNone = lost.cancelled === 0 && lost.noShows === 0;

    return (
        <ReportSection
            title="Lo que se perdió"
            description="Citas que no se hicieron y lo que habrían dejado a precio de lista."
        >
            {hasNone ? (
                <SectionEmpty>
                    Ninguna cancelación ni ausencia en este período.
                </SectionEmpty>
            ) : (
                <dl className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-2 text-sm">
                    <dt>
                        Cancelaciones
                        <span className="text-muted-foreground">
                            {' · '}
                            {countVisits(lost.cancelled)}
                        </span>
                    </dt>
                    <dd className="text-right font-medium tabular-nums">
                        {formatCents(lost.cancelledCents)}
                    </dd>
                    <dt>
                        Ausencias
                        <span className="text-muted-foreground">
                            {' · '}
                            {countVisits(lost.noShows)}
                        </span>
                    </dt>
                    <dd className="text-right font-medium tabular-nums">
                        {formatCents(lost.noShowCents)}
                    </dd>
                    <dt className="border-t pt-2 font-medium">
                        Total no cobrado
                    </dt>
                    <dd className="border-t pt-2 text-right font-medium tabular-nums">
                        {formatCents(lost.cancelledCents + lost.noShowCents)}
                    </dd>
                </dl>
            )}
        </ReportSection>
    );
}

const people = (count: number) =>
    count === 1 ? '1 persona' : `${count} personas`;

/** Among the people attended: first-timers against those who came back. */
export function ClientMix({ clients }: { clients: Mix }) {
    const total = clients.new + clients.returning;
    const newPercent = shareOf(clients.new, total);

    return (
        <ReportSection
            title="Clientes nuevos y recurrentes"
            description="Entre las personas atendidas en el período."
        >
            {total === 0 ? (
                <SectionEmpty>
                    No se atendió a nadie en este período.
                </SectionEmpty>
            ) : (
                <>
                    <dl className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col justify-end">
                            <dt className="text-sm text-muted-foreground">
                                Vinieron por primera vez
                            </dt>
                            <dd className="text-2xl font-semibold tabular-nums">
                                {people(clients.new)}
                            </dd>
                            <dd className="text-sm text-muted-foreground tabular-nums">
                                {newPercent} %
                            </dd>
                        </div>
                        <div className="flex flex-col justify-end">
                            <dt className="text-sm text-muted-foreground">
                                Ya habían venido
                            </dt>
                            <dd className="text-2xl font-semibold tabular-nums">
                                {people(clients.returning)}
                            </dd>
                            <dd className="text-sm text-muted-foreground tabular-nums">
                                {100 - newPercent} %
                            </dd>
                        </div>
                    </dl>
                    <div
                        aria-hidden
                        className="flex h-2 gap-0.5 overflow-hidden rounded-full"
                    >
                        {clients.new > 0 && (
                            <div
                                className="bg-primary"
                                style={{ flexGrow: clients.new }}
                            />
                        )}
                        {clients.returning > 0 && (
                            <div
                                className="bg-muted-foreground/40"
                                style={{ flexGrow: clients.returning }}
                            />
                        )}
                    </div>
                </>
            )}
        </ReportSection>
    );
}
