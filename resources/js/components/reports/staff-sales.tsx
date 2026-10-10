import { CashFigure } from '@/components/cash/cash-figure';
import { formatCents } from '@/lib/format';
import { ReportSection, SectionEmpty } from './report-section';
import type { StaffSales as Sales } from './types';

/** What each professional attended and sold, and what it earned them. */
export function StaffSales({ staff }: { staff: Sales[] }) {
    return (
        <ReportSection
            title="Por profesional"
            description="Citas completadas por cada quien: lo que vendió y la comisión que le toca."
        >
            {staff.length === 0 ? (
                <SectionEmpty>
                    Nadie completó citas en este período.
                </SectionEmpty>
            ) : (
                <ul className="divide-y">
                    {staff.map((member) => (
                        <li
                            key={member.name}
                            aria-label={member.name}
                            className="flex break-inside-avoid flex-col gap-2 py-3 first:pt-0 last:pb-0 lg:flex-row lg:items-center lg:justify-between lg:gap-4"
                        >
                            <p className="min-w-0 font-medium break-words">
                                {member.name}
                            </p>
                            <dl className="grid grid-cols-3 gap-3 lg:w-[24rem] lg:shrink-0">
                                <CashFigure label="Citas">
                                    {member.visits}
                                </CashFigure>
                                <CashFigure label="Ventas">
                                    {formatCents(member.salesCents)}
                                </CashFigure>
                                <CashFigure label="Comisión">
                                    {formatCents(member.commissionCents)}
                                </CashFigure>
                            </dl>
                        </li>
                    ))}
                </ul>
            )}
        </ReportSection>
    );
}
