import { countVisits, describeRules } from '@/lib/commissions';
import { formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';
import { RulesDialog } from './rules-dialog';
import { SettleDialog } from './settle-dialog';
import type { Period, StaffCommission } from './types';

type Props = {
    staff: StaffCommission;
    period: Period;
    /** Only the owner sets the rules. */
    canEditRules: boolean;
    paymentMethods: Option[];
};

/** One professional: their rule, what they are owed and how to pay it. */
export function StaffCommissionCard({
    staff,
    period,
    canEditRules,
    paymentMethods,
}: Props) {
    const isOwed = staff.pendingCents > 0;

    return (
        <li
            aria-label={staff.name}
            className="flex flex-col gap-4 rounded-xl border bg-card p-4 sm:p-5"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold">
                        <span className="min-w-0 break-words">
                            {staff.name}
                        </span>
                        {!staff.isActive && (
                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                Ya no atiende
                            </span>
                        )}
                    </h3>
                    <p className="text-sm break-words text-muted-foreground">
                        {describeRules(staff)}
                    </p>
                </div>
                {canEditRules && <RulesDialog staff={staff} />}
            </div>

            <div className="flex flex-col gap-4 border-t pt-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-sm text-muted-foreground">
                        Comisión pendiente
                    </p>
                    <p
                        className={cn(
                            'text-3xl font-semibold tracking-tight tabular-nums',
                            !isOwed && 'text-muted-foreground',
                        )}
                    >
                        {formatPrice(staff.pending)}
                    </p>
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {isOwed
                            ? `${countVisits(staff.visits)} · ventas ${formatPrice(staff.sales)}`
                            : 'Nada por pagar en este período'}
                    </p>
                </div>
                <SettleDialog
                    staff={staff}
                    period={period}
                    paymentMethods={paymentMethods}
                />
            </div>
        </li>
    );
}
