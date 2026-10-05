import { HOW_IT_WORKS } from './content';
import { LandingSection } from './landing-section';
import { Reveal } from './reveal';
import { SectionHeading } from './section-heading';

const STAGGER_SECONDS = 0.1;

export function HowItWorks() {
    return (
        <LandingSection id="como-funciona" tone="muted">
            <SectionHeading
                eyebrow={HOW_IT_WORKS.eyebrow}
                title={HOW_IT_WORKS.title}
            />
            <div className="relative mt-12">
                {/* Connector line between the step numbers on desktop */}
                <div
                    aria-hidden
                    className="absolute top-6 right-[16%] left-[16%] hidden border-t-2 border-dashed border-primary/40 md:block"
                />
                <ol className="relative grid gap-10 md:grid-cols-3 md:gap-6">
                    {HOW_IT_WORKS.steps.map((step, index) => (
                        <li key={step.title} className="relative">
                            <Reveal
                                delay={index * STAGGER_SECONDS}
                                className="flex flex-col items-start gap-4 md:items-center md:text-center"
                            >
                                <span className="flex size-12 items-center justify-center rounded-full bg-primary font-display text-xl font-semibold text-primary-foreground tabular-nums">
                                    {index + 1}
                                </span>
                                <h3 className="text-xl font-semibold text-balance">
                                    {step.title}
                                </h3>
                                <p className="max-w-xs text-base text-pretty text-muted-foreground">
                                    {step.description}
                                </p>
                            </Reveal>
                        </li>
                    ))}
                </ol>
            </div>
        </LandingSection>
    );
}
