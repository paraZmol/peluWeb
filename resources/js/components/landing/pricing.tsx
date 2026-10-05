import { Gift } from 'lucide-react';
import { PRICING } from './content';
import { LandingSection } from './landing-section';
import { PlanCard } from './plan-card';
import { Reveal } from './reveal';
import { SectionHeading } from './section-heading';

const STAGGER_SECONDS = 0.08;

export function Pricing() {
    return (
        <LandingSection id="precios" tone="muted">
            <SectionHeading eyebrow={PRICING.eyebrow} title={PRICING.title} />

            <Reveal className="mt-6 flex justify-center">
                <p className="inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-4 py-2 text-center text-sm font-medium">
                    <Gift
                        className="size-4 shrink-0 text-primary"
                        aria-hidden
                    />
                    {PRICING.trialNote}
                </p>
            </Reveal>

            <ul className="mx-auto mt-14 grid max-w-md gap-8 md:max-w-none md:grid-cols-3 md:gap-6">
                {PRICING.plans.map((plan, index) => (
                    <li key={plan.name}>
                        <Reveal
                            delay={index * STAGGER_SECONDS}
                            className="h-full"
                        >
                            <PlanCard plan={plan} />
                        </Reveal>
                    </li>
                ))}
            </ul>

            <p className="mt-10 text-center text-sm text-muted-foreground">
                {PRICING.footnote}
            </p>
        </LandingSection>
    );
}
