import { countVisits, formatPeriod, pendingTotals } from '@/lib/commissions';
import { formatCents } from '@/lib/format';
import type { Period, StaffCommission } from './types';

type Props = { staff: StaffCommission[]; period: Period };

/** The period at a glance: what the whole team is still owed. */
export function PendingSummary({ staff, period }: Props) {
    const totals = pendingTotals(staff);

    return (
        <section
            aria-label="Pendiente del período"
            className="flex flex-col gap-5 rounded-xl border bg-card p-5 shadow-card"
        >
            <div>
                <p className="text-sm text-muted-foreground">
                    Comisiones por pagar
                </p>
                <p
                    data-testid="commissions-pending"
                    className="text-4xl font-semibold tracking-tight tabular-nums"
                >
                    {formatCents(totals.cents)}
                </p>
                <p className="text-sm text-muted-foreground">
                    {formatPeriod(period)}
                </p>
            </div>

            {totals.cents === 0 ? (
                <p className="border-t pt-4 text-sm text-muted-foreground">
                    No hay nada pendiente en este período. Las comisiones
                    aparecen aquí cuando una cita se completa.
                </p>
            ) : (
                <dl className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1.5 border-t pt-4 text-sm">
                    <dt className="text-muted-foreground">Citas completadas</dt>
                    <dd className="text-right tabular-nums">
                        {countVisits(totals.visits)}
                    </dd>
                    <dt className="text-muted-foreground">A quiénes pagar</dt>
                    <dd className="text-right tabular-nums">
                        {totals.people === 1
                            ? '1 profesional'
                            : `${totals.people} profesionales`}
                    </dd>
                </dl>
            )}
        </section>
    );
}
