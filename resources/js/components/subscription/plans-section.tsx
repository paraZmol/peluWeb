import { PlanCard } from './plan-card';
import type { Subscription } from './types';

type Props = {
    subscription: Pick<Subscription, 'plans' | 'offersYear' | 'isFounder'>;
};

/** The plans the salon can be on, at its own prices. */
export function PlansSection({ subscription }: Props) {
    return (
        <section aria-labelledby="plans-title" className="flex flex-col gap-3">
            <div className="px-1">
                <h2 id="plans-title" className="text-lg font-semibold">
                    Planes
                </h2>
                <p className="text-sm text-muted-foreground">
                    Todo incluido en los dos planes. Pagas por quien atiende con
                    agenda; recepción y caja van gratis.
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                {subscription.plans.map((plan) => (
                    <PlanCard
                        key={plan.code}
                        plan={plan}
                        offersYear={subscription.offersYear}
                        isFounder={subscription.isFounder}
                    />
                ))}
            </div>

            <p className="px-1 text-sm text-muted-foreground">
                ¿Más de 6 profesionales? Escríbenos y te preparamos un precio
                para tu salón.
            </p>
        </section>
    );
}
