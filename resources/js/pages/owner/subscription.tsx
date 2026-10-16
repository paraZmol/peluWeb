import { Head } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import type { ReactNode } from 'react';
import { HowToPay } from '@/components/subscription/how-to-pay';
import { PaymentHistory } from '@/components/subscription/payment-history';
import { PlansSection } from '@/components/subscription/plans-section';
import { StandingCard } from '@/components/subscription/standing-card';
import type { Subscription } from '@/components/subscription/types';
import { show } from '@/routes/subscription';

type Props = {
    subscription: Subscription;
    currency: string;
};

/**
 * "Mi plan": where the salon stands with the platform and how to pay.
 * Payments are recorded by the platform admin; the owner only reads here.
 */
export default function MyPlan({ subscription }: Props) {
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

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="flex min-w-0 flex-col gap-8">
                        <Rise order={0}>
                            <StandingCard subscription={subscription} />
                        </Rise>
                        <Rise order={1}>
                            <PlansSection subscription={subscription} />
                        </Rise>
                    </div>

                    <Rise order={2} className="lg:sticky lg:top-6">
                        <HowToPay payTo={subscription.payTo} />
                    </Rise>

                    <Rise order={3} className="min-w-0">
                        <PaymentHistory payments={subscription.payments} />
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
