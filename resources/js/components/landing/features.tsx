import { FEATURES, FEATURES_HEADING } from './content';
import { IconCard } from './icon-card';
import { LandingSection } from './landing-section';
import { Reveal } from './reveal';
import { SectionHeading } from './section-heading';

const COLUMNS = 3;
const STAGGER_SECONDS = 0.06;

export function Features() {
    return (
        <LandingSection id="funciones">
            <SectionHeading {...FEATURES_HEADING} />
            <ul className="mt-12 grid gap-4 sm:grid-cols-2 md:gap-6 lg:grid-cols-3">
                {FEATURES.map((feature, index) => (
                    <li key={feature.title}>
                        <Reveal
                            delay={(index % COLUMNS) * STAGGER_SECONDS}
                            className="h-full"
                        >
                            <IconCard item={feature} />
                        </Reveal>
                    </li>
                ))}
            </ul>
        </LandingSection>
    );
}
