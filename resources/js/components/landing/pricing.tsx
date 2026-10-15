import { Gift } from 'lucide-react';
import { formatLongDate } from '@/lib/format';
import { founderFootnote, PRICING } from './content';
import type { PlanCopy, PublicPricing } from './content';
import { LandingSection } from './landing-section';
import { PlanCard } from './plan-card';
import { Reveal } from './reveal';
import { SectionHeading } from './section-heading';

const STAGGER_SECONDS = 0.08;

export function Pricing({ pricing }: { pricing: PublicPricing }) {
    // Only plans the landing has words for: a new public plan needs its copy first.
    const plans = pricing.plans.flatMap((plan) => {
        const copy: PlanCopy | undefined =
            PRICING.plans[plan.code as keyof typeof PRICING.plans];

        return copy ? [{ plan, copy }] : [];
    });
    const founder = pricing.founder;

    return (
        <LandingSection id="precios" tone="muted">
            <SectionHeading eyebrow={PRICING.eyebrow} title={PRICING.title} />

            <Reveal className="mt-6 flex justify-center">
                <p className="inline-flex items-center gap-2 rounded-full border border-brand-line bg-brand-soft px-4 py-2 text-center text-sm font-medium">
                    <Gift
                        className="size-4 shrink-0 text-brand-text"
                        aria-hidden
                    />
                    {PRICING.trialNote}
                </p>
            </Reveal>

            <ul className="mx-auto mt-14 grid max-w-md gap-8 md:max-w-3xl md:grid-cols-2 md:gap-6">
                {plans.map(({ plan, copy }, index) => (
                    <li key={plan.code}>
                        <Reveal
                            delay={index * STAGGER_SECONDS}
                            className="h-full"
                        >
                            <PlanCard
                                plan={plan}
                                copy={copy}
                                founder={founder}
                            />
                        </Reveal>
                    </li>
                ))}
            </ul>

            <p className="mx-auto mt-10 max-w-2xl text-center text-base">
                {PRICING.underCards}
            </p>
            <p className="mx-auto mt-4 max-w-2xl text-center text-sm text-muted-foreground">
                {PRICING.footnote}
                {founder &&
                    ` ${founderFootnote(
                        founder.slotsLeft,
                        founder.endsOn ? formatLongDate(founder.endsOn) : null,
                    )}`}
            </p>
        </LandingSection>
    );
}
