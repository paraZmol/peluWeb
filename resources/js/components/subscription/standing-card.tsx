import { CircleAlert, CircleCheck, Clock, PauseCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { standingDetail, standingHeadline } from '@/lib/subscription';
import { cn } from '@/lib/utils';
import { AgendaMeter } from './agenda-meter';
import type { Subscription, SubscriptionState } from './types';

const STATE_LOOK: Record<
    SubscriptionState,
    { icon: typeof Clock; className: string }
> = {
    trial: { icon: Clock, className: 'bg-brand-soft text-brand-text' },
    active: { icon: CircleCheck, className: 'bg-success/10 text-success' },
    grace: { icon: CircleAlert, className: 'bg-warning/10 text-warning' },
    read_only: {
        icon: PauseCircle,
        className: 'bg-destructive/10 text-destructive-foreground',
    },
    suspended: {
        icon: PauseCircle,
        className: 'bg-destructive/10 text-destructive-foreground',
    },
};

type Props = { subscription: Subscription };

/** Where the salon stands today, its plan and the agendas it uses. */
export function StandingCard({ subscription }: Props) {
    const { state, plan } = subscription;
    const look = STATE_LOOK[state];
    const standing = { ...subscription, planName: plan.name };

    return (
        <section
            aria-labelledby="standing-title"
            className="flex flex-col gap-5 rounded-xl border bg-card p-5 shadow-card sm:p-6"
        >
            <div className="flex flex-col gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium',
                            look.className,
                        )}
                    >
                        <look.icon className="size-4" aria-hidden />
                        {subscription.stateLabel}
                    </span>
                    {subscription.isFounder && (
                        <Badge variant="secondary">Precio fundador</Badge>
                    )}
                </div>
                <h2
                    id="standing-title"
                    className="font-display text-xl leading-snug font-semibold text-balance sm:text-2xl"
                >
                    {standingHeadline(standing)}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {standingDetail(standing)}
                </p>
            </div>

            <div className="grid gap-4 border-t pt-5 sm:grid-cols-[auto_minmax(0,1fr)] sm:items-end sm:gap-8">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {state === 'trial' ? 'Plan de prueba' : 'Tu plan'}
                    </p>
                    <p className="text-lg font-semibold">Plan {plan.name}</p>
                </div>
                <AgendaMeter
                    withAgenda={subscription.withAgenda}
                    max={plan.maxStaff}
                />
            </div>
        </section>
    );
}
