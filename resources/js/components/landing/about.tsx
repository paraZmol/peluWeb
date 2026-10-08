import { ABOUT } from './content';
import { LandingSection } from './landing-section';
import { Reveal } from './reveal';
import { SectionHeading } from './section-heading';

const STAGGER_SECONDS = 0.08;

export function About() {
    return (
        <LandingSection id="nosotros" tone="card">
            <div className="grid gap-10 lg:grid-cols-2 lg:gap-16">
                <div className="flex flex-col gap-6">
                    <SectionHeading
                        eyebrow={ABOUT.eyebrow}
                        title={ABOUT.title}
                        align="start"
                    />
                    <Reveal className="flex flex-col gap-4">
                        {ABOUT.paragraphs.map((paragraph) => (
                            <p
                                key={paragraph}
                                className="text-base text-pretty text-muted-foreground md:text-lg"
                            >
                                {paragraph}
                            </p>
                        ))}
                    </Reveal>
                </div>

                <ul className="flex flex-col gap-4 lg:justify-center">
                    {ABOUT.values.map((value, index) => {
                        const Icon = value.icon;

                        return (
                            <li key={value.title}>
                                <Reveal
                                    delay={index * STAGGER_SECONDS}
                                    className="flex items-start gap-4 rounded-3xl p-5 skin-card"
                                >
                                    <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand-text">
                                        <Icon className="size-5" aria-hidden />
                                    </span>
                                    <div className="flex flex-col gap-1">
                                        <h3 className="text-lg font-semibold">
                                            {value.title}
                                        </h3>
                                        <p className="text-base text-muted-foreground">
                                            {value.description}
                                        </p>
                                    </div>
                                </Reveal>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </LandingSection>
    );
}
