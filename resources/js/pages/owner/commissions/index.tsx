import { Head, Link } from '@inertiajs/react';
import { Contact } from 'lucide-react';
import { PendingSummary } from '@/components/commissions/pending-summary';
import { PeriodPicker } from '@/components/commissions/period-picker';
import { SettlementHistory } from '@/components/commissions/settlement-history';
import { StaffCommissionCard } from '@/components/commissions/staff-commission-card';
import type {
    Settlement,
    StaffCommission,
} from '@/components/commissions/types';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/commissions';
import { index as staffIndex } from '@/routes/staff';
import type { Option } from '@/types';

type Props = {
    /** The period on screen, both days included ("2026-10-01"). */
    from: string;
    to: string;
    /** Today on the salon's calendar. */
    today: string;
    staff: StaffCommission[];
    /** The last settlements, newest first. */
    history: Settlement[];
    /** Only the owner sets the rules; paying needs the cash permission. */
    canEditRules: boolean;
    paymentMethods: Option[];
};

export default function Commissions({
    from,
    to,
    today,
    staff,
    history,
    canEditRules,
    paymentMethods,
}: Props) {
    const period = { from, to };

    return (
        <>
            <Head title="Comisiones" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6">
                <div>
                    <h1 className="text-2xl font-semibold">Comisiones</h1>
                    <p className="text-sm text-muted-foreground">
                        Lo que gana cada profesional por las citas que completa,
                        y lo que falta pagarle.
                    </p>
                </div>

                <PeriodPicker period={period} today={today} />

                <div className="grid items-start gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
                    <div className="lg:sticky lg:top-6">
                        <PendingSummary staff={staff} period={period} />
                    </div>

                    <div className="flex min-w-0 flex-col gap-6">
                        <section
                            aria-labelledby="commissions-staff-title"
                            className="flex flex-col gap-2"
                        >
                            <h2
                                id="commissions-staff-title"
                                className="px-1 text-sm font-medium text-muted-foreground"
                            >
                                Por profesional
                            </h2>
                            {staff.length === 0 ? (
                                <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center">
                                    <Contact
                                        className="size-6 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <p className="text-muted-foreground">
                                        Todavía no hay profesionales en este
                                        salón. Cuando alguien atienda citas,
                                        aquí verás su comisión.
                                    </p>
                                    {canEditRules && (
                                        <Button asChild>
                                            <Link href={staffIndex()}>
                                                Agregar profesionales
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            ) : (
                                <ul className="flex flex-col gap-3">
                                    {staff.map((member) => (
                                        <StaffCommissionCard
                                            key={member.id}
                                            staff={member}
                                            period={period}
                                            canEditRules={canEditRules}
                                            paymentMethods={paymentMethods}
                                        />
                                    ))}
                                </ul>
                            )}
                        </section>

                        <SettlementHistory history={history} />
                    </div>
                </div>
            </div>
        </>
    );
}

Commissions.layout = {
    breadcrumbs: [{ title: 'Comisiones', href: index() }],
};
