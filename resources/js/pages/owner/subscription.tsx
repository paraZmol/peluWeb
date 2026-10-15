import { Head } from '@inertiajs/react';
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

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6">
                <h1 className="text-2xl font-semibold">Mi plan</h1>
                <p>
                    {subscription.plan.name} · {subscription.stateLabel}
                </p>
            </div>
        </>
    );
}

MyPlan.layout = {
    breadcrumbs: [{ title: 'Mi plan', href: show() }],
};
