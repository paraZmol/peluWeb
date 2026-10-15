import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { buttonVariants } from '@/components/ui/button';
import { formatCents, formatLongDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { register } from '@/routes';
import { founderLine, PRICING } from './content';
import type { PlanCopy, PublicPlan, PublicPricing } from './content';

/** 3900 → "39", 3950 → "39.50": the big number on the card. */
function soles(cents: number): string {
    return cents % 100 === 0 ? String(cents / 100) : (cents / 100).toFixed(2);
}

type Props = {
    plan: PublicPlan;
    copy: PlanCopy;
    founder: PublicPricing['founder'];
};

export function PlanCard({ plan, copy, founder }: Props) {
    const isFeatured = copy.isFeatured === true;
    const founderText =
        founder && plan.founderPriceCents !== null
            ? founderLine(
                  formatCents(plan.founderPriceCents),
                  formatCents(plan.priceCents),
                  founder.slotsLeft,
                  founder.endsOn ? formatLongDate(founder.endsOn) : null,
              )
            : null;

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
                    {copy.description}
                </p>
            </div>

            <div className="flex flex-col gap-1">
                <p className="flex items-baseline gap-1">
                    <span className="text-lg font-medium text-muted-foreground">
                        S/
                    </span>
                    <span className="font-display text-5xl font-semibold tracking-tight tabular-nums">
                        {soles(plan.priceCents)}
                    </span>
                    <span className="text-base text-muted-foreground">
                        {PRICING.perMonth}
                    </span>
                </p>
                <p className="text-sm font-medium text-brand-text">
                    {copy.capacity}
                </p>
            </div>

            {founderText && (
                <p className="rounded-2xl border border-brand-line bg-brand-soft px-4 py-3 text-sm">
                    {founderText}
                </p>
            )}

            <ul className="flex flex-1 flex-col gap-3 border-t pt-6">
                {copy.features.map((feature) => (
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
