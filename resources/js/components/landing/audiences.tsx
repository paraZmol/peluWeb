import { AUDIENCES, AUDIENCES_HEADING } from './content';
import { IconCard } from './icon-card';
import { LandingSection } from './landing-section';
import { Reveal } from './reveal';
import { SectionHeading } from './section-heading';

const STAGGER_SECONDS = 0.05;

export function Audiences() {
    return (
        <LandingSection id="para-quien">
            <SectionHeading {...AUDIENCES_HEADING} />
            {/* Flex-wrap instead of grid so the last row of 5 cards stays centered */}
            <ul className="mt-12 flex flex-wrap justify-center gap-4 md:gap-6">
                {AUDIENCES.map((audience, index) => (
                    <li
                        key={audience.title}
                        className="w-full sm:w-[calc(50%-0.5rem)] md:w-[calc(50%-0.75rem)] lg:w-[calc(33.333%-1rem)]"
                    >
                        <Reveal
                            delay={index * STAGGER_SECONDS}
                            className="h-full"
                        >
                            <IconCard item={audience} />
                        </Reveal>
                    </li>
                ))}
            </ul>
        </LandingSection>
    );
}
