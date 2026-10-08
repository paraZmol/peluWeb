import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { register } from '@/routes';
import { PRICING } from './content';
import type { Plan } from './content';

export function PlanCard({ plan }: { plan: Plan }) {
    const isFeatured = plan.isFeatured === true;

    return (
        <article
            className={cn(
                'relative flex h-full flex-col gap-6 rounded-3xl p-6 md:p-8',
                isFeatured
                    ? 'skin-raised outline-2 outline-primary'
                    : 'skin-card',
            )}
        >
            {isFeatured && (
                <span className="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-primary px-3 py-1 text-xs font-semibold whitespace-nowrap text-primary-foreground">
                    {PRICING.featuredLabel}
                </span>
            )}

            <div className="flex flex-col gap-1">
                <h3 className="font-display text-2xl font-semibold">
                    {plan.name}
                </h3>
                <p className="text-sm text-muted-foreground">
                    {plan.description}
                </p>
            </div>

            <div className="flex flex-col gap-1">
                <p className="flex items-baseline gap-1">
                    <span className="text-lg font-medium text-muted-foreground">
                        S/
                    </span>
                    <span className="font-display text-5xl font-semibold tracking-tight tabular-nums">
                        {plan.priceSoles}
                    </span>
                    <span className="text-base text-muted-foreground">
                        {PRICING.perMonth}
                    </span>
                </p>
                <p className="text-sm font-medium text-brand-text">
                    {plan.capacity}
                </p>
            </div>

            <ul className="flex flex-1 flex-col gap-3 border-t pt-6">
                {plan.features.map((feature) => (
                    <li key={feature} className="flex items-start gap-2.5">
                        <Check
                            className="mt-0.5 size-4 shrink-0 text-brand-text"
                            strokeWidth={2.5}
                            aria-hidden
                        />
                        <span className="text-base">{feature}</span>
                    </li>
                ))}
            </ul>

            <Link
                href={register()}
                className={cn(
                    buttonVariants({
                        variant: isFeatured ? 'default' : 'outline',
                        size: 'lg',
                    }),
                    'w-full rounded-full md:h-12',
                )}
            >
                {PRICING.cta}
            </Link>
        </article>
    );
}
