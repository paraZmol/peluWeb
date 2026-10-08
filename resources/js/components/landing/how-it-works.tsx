import { CalendarCheck, Link2, Store } from 'lucide-react';
import { HOW_IT_WORKS } from './content';
import { LandingSection } from './landing-section';
import { Reveal } from './reveal';
import { SectionHeading } from './section-heading';

const STAGGER_SECONDS = 0.1;
const STEP_ICONS = [Store, Link2, CalendarCheck];

export function HowItWorks() {
    return (
        <LandingSection id="como-funciona">
            <SectionHeading
                eyebrow={HOW_IT_WORKS.eyebrow}
                title={HOW_IT_WORKS.title}
            />
            <ol className="mt-12 grid gap-4 md:grid-cols-3 md:gap-6">
                {HOW_IT_WORKS.steps.map((step, index) => {
                    const Icon = STEP_ICONS[index];

                    return (
                        <li key={step.title}>
                            <Reveal
                                delay={index * STAGGER_SECONDS}
                                className="flex h-full flex-col gap-4 rounded-3xl p-6 skin-card md:p-7"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="font-display text-5xl leading-none font-semibold text-brand-text tabular-nums">
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    <span className="flex size-11 items-center justify-center rounded-2xl bg-brand-soft text-brand-text">
                                        <Icon
                                            className="size-5"
                                            strokeWidth={1.75}
                                            aria-hidden
                                        />
                                    </span>
                                </div>
                                <h3 className="text-xl font-semibold text-balance">
                                    {step.title}
                                </h3>
                                <p className="text-base text-pretty text-muted-foreground">
                                    {step.description}
                                </p>
                            </Reveal>
                        </li>
                    );
                })}
            </ol>
        </LandingSection>
    );
}
