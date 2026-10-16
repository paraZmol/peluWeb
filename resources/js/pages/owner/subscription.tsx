import { Head } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import { HowToPay } from '@/components/subscription/how-to-pay';
import { PaymentHistory } from '@/components/subscription/payment-history';
import { PlansSection } from '@/components/subscription/plans-section';
import { StandingCard } from '@/components/subscription/standing-card';
import { SupportVisits } from '@/components/subscription/support-visits';
import type {
    Subscription,
    SubscriptionState,
} from '@/components/subscription/types';
import { cn } from '@/lib/utils';
import { show } from '@/routes/subscription';

type Props = {
    subscription: Subscription;
    currency: string;
};

/** Late or paused: paying comes first. */
const OVERDUE_STATES: SubscriptionState[] = ['grace', 'read_only'];

/**
 * "Mi plan": where the salon stands with the platform and how to pay.
 * Payments are recorded by the platform admin; the owner only reads here.
 */
export default function MyPlan({ subscription }: Props) {
    const isOverdue = OVERDUE_STATES.includes(subscription.state);

    return (
        <>
            <Head title="Mi plan" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6">
                <div>
                    <h1 className="text-2xl font-semibold">Mi plan</h1>
                    <p className="text-sm text-muted-foreground">
                        Lo que pagas por usar peluWeb, cómo pagar y lo que ya
                        pagaste.
                    </p>
                </div>

                {/* Phones read top to bottom: a late or paused salon gets how to
                    pay right under its standing, before the plans. On desktop
                    it stays in the side column. */}
                <div className="grid items-start gap-x-6 gap-y-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <Rise
                        order={0}
                        className="min-w-0 lg:col-start-1 lg:row-start-1"
                    >
                        <StandingCard subscription={subscription} />
                    </Rise>
                    <Rise
                        order={isOverdue ? 1 : 2}
                        className={cn(
                            'lg:sticky lg:top-6 lg:col-start-2 lg:row-span-3 lg:row-start-1',
                            !isOverdue && 'max-lg:order-1',
                        )}
                    >
                        <HowToPay
                            payTo={subscription.payTo}
                            dueCents={subscription.dueCents}
                            planName={subscription.plan.name}
                        />
                    </Rise>
                    <Rise
                        order={isOverdue ? 2 : 1}
                        className="min-w-0 lg:col-start-1 lg:row-start-2"
                    >
                        <PlansSection subscription={subscription} />
                    </Rise>

                    <Rise
                        order={3}
                        className="flex min-w-0 flex-col gap-8 max-lg:order-2 lg:col-start-1 lg:row-start-3"
                    >
                        <PaymentHistory payments={subscription.payments} />
                        <SupportVisits visits={subscription.supportVisits} />
                    </Rise>
                </div>
            </div>
        </>
    );
}

/** The page's blocks come in once, one after the other. */
function Rise({
    order,
    className,
    children,
}: {
    order: number;
    className?: string;
    children: ReactNode;
}) {
    const reduceMotion = useReducedMotion();

    return (
        <motion.div
            initial={{ opacity: 0, y: reduceMotion ? 0 : 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{
                duration: 0.25,
                delay: order * 0.06,
                ease: [0.22, 1, 0.36, 1],
            }}
            className={className}
        >
            {children}
        </motion.div>
    );
}

MyPlan.layout = {
    breadcrumbs: [{ title: 'Mi plan', href: show() }],
};
