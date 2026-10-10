import { Head } from '@inertiajs/react';
import { PeriodPicker } from '@/components/commissions/period-picker';
import { DailyChart } from '@/components/reports/daily-chart';
import { ClientMix, LostVisits } from '@/components/reports/lost-and-clients';
import { MoneyReceived } from '@/components/reports/money-received';
import { Occupancy } from '@/components/reports/occupancy';
import {
    EmptyReport,
    PrintHeader,
    ReportActions,
} from '@/components/reports/report-actions';
import {
    ExpenseCategories,
    IncomeSources,
    TopServices,
} from '@/components/reports/report-breakdowns';
import { ReportTotals } from '@/components/reports/report-totals';
import { StaffSales } from '@/components/reports/staff-sales';
import type { Report } from '@/components/reports/types';
import { isEmptyReport } from '@/lib/reports';
import { index } from '@/routes/reports';

type Props = {
    /** The period on screen, both days included ("2026-10-01"). */
    from: string;
    to: string;
    /** The period right before it, of the same length. */
    previousFrom: string;
    previousTo: string;
    /** Today on the salon's calendar. */
    today: string;
    report: Report;
};

export default function Reports({
    from,
    to,
    previousFrom,
    previousTo,
    today,
    report,
}: Props) {
    const period = { from, to };

    return (
        <>
            <Head title="Reportes" />

            <div className="report-sheet mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between print:hidden">
                    <div>
                        <h1 className="text-2xl font-semibold">Reportes</h1>
                        <p className="text-sm text-muted-foreground">
                            Cuánto entró, cuánto salió y qué quedó en el período
                            que elijas.
                        </p>
                    </div>
                    <ReportActions period={period} />
                </div>

                <div className="print:hidden">
                    <PeriodPicker period={period} today={today} route={index} />
                </div>

                <PrintHeader period={period} />

                <ReportTotals
                    totals={report.totals}
                    previous={report.previous}
                    previousPeriod={{ from: previousFrom, to: previousTo }}
                />

                {isEmptyReport(report) ? (
                    <EmptyReport />
                ) : (
                    <>
                        <DailyChart days={report.days} />

                        <div className="grid items-start gap-6 lg:grid-cols-2">
                            <IncomeSources totals={report.totals} />
                            <ExpenseCategories categories={report.byCategory} />
                        </div>

                        <StaffSales staff={report.byStaff} />

                        <Occupancy staff={report.occupancy} />

                        <div className="grid items-start gap-6 lg:grid-cols-2">
                            <TopServices services={report.byService} />
                            <MoneyReceived methods={report.byMethod} />
                        </div>

                        <div className="grid items-start gap-6 lg:grid-cols-2">
                            <LostVisits lost={report.lost} />
                            <ClientMix clients={report.clients} />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Reports.layout = {
    breadcrumbs: [{ title: 'Reportes', href: index() }],
};
