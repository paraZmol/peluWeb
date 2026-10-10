import { countVisits } from '@/lib/commissions';
import { formatCents } from '@/lib/format';
import { ReportSection, SectionEmpty } from './report-section';
import { ShareList } from './share-list';
import type { NamedAmount, ReportTotals, ServiceSales } from './types';

/** Services and kept deposits; discounts apart, with their why. */
export function IncomeSources({ totals }: { totals: ReportTotals }) {
    return (
        <ReportSection
            title="De dónde vino el ingreso"
            description="Lo que dejaron las citas completadas, el día en que se atendieron."
        >
            {totals.incomeCents === 0 ? (
                <SectionEmpty>
                    Todavía no hay ingresos en este período. Aparecen cuando una
                    cita se completa y se cobra.
                </SectionEmpty>
            ) : (
                <ShareList
                    totalCents={totals.incomeCents}
                    items={[
                        {
                            name: 'Servicios',
                            cents: totals.servicesCents,
                            detail: countVisits(totals.visits),
                        },
                        {
                            name: 'Señas no devueltas',
                            cents: totals.keptDepositsCents,
                            detail: 'de citas canceladas tarde o sin asistencia',
                        },
                    ]}
                />
            )}

            <dl className="flex flex-col gap-3 border-t pt-4 text-sm">
                <Aside label="Descuentos" cents={totals.discountsCents}>
                    Lo que se rebajó al cobrar: ya está restado de los
                    servicios.
                </Aside>
            </dl>
        </ReportSection>
    );
}

type AsideProps = { label: string; cents: number; children: string };

function Aside({ label, cents, children }: AsideProps) {
    return (
        <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4">
            <dt className="font-medium">{label}</dt>
            <dd className="text-right font-medium tabular-nums">
                {formatCents(cents)}
            </dd>
            <dd className="col-span-2 text-muted-foreground">{children}</dd>
        </div>
    );
}

/** What the money was spent on, biggest first. */
export function ExpenseCategories({
    categories,
}: {
    categories: NamedAmount[];
}) {
    return (
        <ReportSection
            title="Gastos por categoría"
            description="Lo pagado en el período, con las comisiones liquidadas."
        >
            {categories.length === 0 ? (
                <SectionEmpty>
                    No hay gastos registrados en este período.
                </SectionEmpty>
            ) : (
                <ShareList items={categories} />
            )}
        </ReportSection>
    );
}

/** The services that sold the most. */
export function TopServices({ services }: { services: ServiceSales[] }) {
    return (
        <ReportSection
            title="Servicios más vendidos"
            description="A precio de lista, sin contar descuentos. El porcentaje es sobre el total de esta lista."
        >
            {services.length === 0 ? (
                <SectionEmpty>
                    Ningún servicio completado en este período.
                </SectionEmpty>
            ) : (
                <ShareList
                    items={services.map((service) => ({
                        name: service.name,
                        cents: service.cents,
                        detail:
                            service.count === 1
                                ? '1 vez'
                                : `${service.count} veces`,
                    }))}
                />
            )}
        </ReportSection>
    );
}
