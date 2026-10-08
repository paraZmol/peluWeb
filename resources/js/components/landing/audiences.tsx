import { AUDIENCES, AUDIENCES_HEADING, AUDIENCES_MORE } from './content';
import type { PhotoItem } from './content';
import { LandingSection } from './landing-section';
import { Reveal } from './reveal';
import { SectionHeading } from './section-heading';

const STAGGER_SECONDS = 0.08;

function AudiencePhoto({ item }: { item: PhotoItem }) {
    return (
        <figure className="photo-frame aspect-[4/5] rounded-3xl shadow-raised">
            <img
                src={item.photo.src}
                alt={item.photo.alt}
                loading="lazy"
                className="absolute inset-0"
            />
            <div aria-hidden className="absolute inset-0 photo-veil" />
            <figcaption className="absolute inset-x-0 bottom-0 z-10 flex flex-col gap-1.5 p-5 text-on-photo md:p-6">
                <h3 className="font-display text-2xl font-semibold md:text-[1.75rem]">
                    {item.title}
                </h3>
                <p className="text-base text-pretty text-on-photo/85">
                    {item.description}
                </p>
            </figcaption>
        </figure>
    );
}

/** "Hecho para negocios como el tuyo": a real photo per kind of business. */
export function Audiences() {
    return (
        <LandingSection id="para-quien">
            <SectionHeading {...AUDIENCES_HEADING} />

            {/* A swipeable row on phones, three columns from tablet up */}
            <ul className="-mx-4 mt-12 -mb-6 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-8 sm:mx-0 sm:mb-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 sm:pb-0 md:gap-6">
                {AUDIENCES.map((item, index) => (
                    <li
                        key={item.title}
                        className="w-[78%] shrink-0 snap-center sm:w-auto"
                    >
                        <Reveal delay={index * STAGGER_SECONDS}>
                            <AudiencePhoto item={item} />
                        </Reveal>
                    </li>
                ))}
            </ul>

            <ul className="mt-6 grid gap-4 sm:grid-cols-2 md:gap-6">
                {AUDIENCES_MORE.map((item, index) => {
                    const Icon = item.icon;

                    return (
                        <li key={item.title}>
                            <Reveal
                                delay={index * STAGGER_SECONDS}
                                className="flex h-full items-start gap-4 rounded-3xl p-5 skin-card"
                            >
                                <span className="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-brand-soft text-brand-text">
                                    <Icon
                                        className="size-5"
                                        strokeWidth={1.75}
                                        aria-hidden
                                    />
                                </span>
                                <div className="flex flex-col gap-1">
                                    <h3 className="text-lg font-semibold">
                                        {item.title}
                                    </h3>
                                    <p className="text-base text-pretty text-muted-foreground">
                                        {item.description}
                                    </p>
                                </div>
                            </Reveal>
                        </li>
                    );
                })}
            </ul>
        </LandingSection>
    );
}
