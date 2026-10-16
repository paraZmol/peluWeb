import { Badge } from '@/components/ui/badge';
import { formatCents } from '@/lib/format';
import { staffLimitText } from '@/lib/subscription';
import { cn } from '@/lib/utils';
import type { SubscriptionPlan } from './types';

type Props = {
    plan: SubscriptionPlan;
    /** The year price is offered from the second payment on. */
    offersYear: boolean;
    /** The salon already holds its founder price (it paid while there were places). */
    isFounder: boolean;
};

/**
 * One plan at what this salon would pay. A founder price shows the normal
 * price beside it as plain reference: it is a real price, never struck out.
 */
export function PlanCard({ plan, offersYear, isFounder }: Props) {
    const isFounderPrice = plan.monthlyCents !== plan.normalCents;

    return (
        <article
            aria-labelledby={`plan-${plan.code}`}
            className={cn(
                'flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-card',
                plan.isCurrent && 'border-brand-line ring-1 ring-brand-line',
            )}
        >
            <header className="flex flex-wrap items-center justify-between gap-2">
                <h3
                    id={`plan-${plan.code}`}
                    className="font-display text-xl font-semibold"
                >
                    {plan.name}
                </h3>
                {plan.isCurrent && (
                    <Badge className="bg-brand-soft text-brand-text">
                        Tu plan
                    </Badge>
                )}
            </header>

            <div>
                {isFounderPrice && (
                    <p className="text-xs font-bold tracking-[.16em] text-brand-text uppercase">
                        Precio fundador
                    </p>
                )}
                <p className="flex items-baseline gap-1.5">
                    <span className="text-3xl font-semibold tracking-tight tabular-nums">
                        {formatCents(plan.monthlyCents)}
                    </span>
                    <span className="text-sm text-muted-foreground">
                        al mes
                    </span>
                </p>
                {isFounderPrice && (
                    <p className="text-sm text-muted-foreground">
                        Precio normal:{' '}
                        <span className="tabular-nums">
                            {formatCents(plan.normalCents)}
                        </span>{' '}
                        al mes.
                    </p>
                )}
            </div>

            {isFounderPrice && isFounder && (
                <p className="border-l-2 border-brand-line pl-3 text-sm text-pretty text-muted-foreground">
                    Mantienes este precio mientras pagues a tiempo. Si el salón
                    entra en pausa, pagas el precio normal desde el siguiente
                    pago.
                </p>
            )}

            <p className="text-sm font-medium">
                {staffLimitText(plan.maxStaff)}
            </p>

            {offersYear && (
                <p className="rounded-lg bg-muted px-3 py-2 text-sm">
                    O{' '}
                    <span className="font-semibold tabular-nums">
                        {formatCents(plan.yearCents)}
                    </span>{' '}
                    al año: 12 meses por el precio de 10. El pago anual no se
                    devuelve: si dejas de usar peluWeb antes, tu plan sigue
                    activo hasta el final de los 12 meses.
                </p>
            )}
        </article>
    );
}
