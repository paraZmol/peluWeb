import { useSalonTheme } from '@/components/salon/brand-theme';
import { cn } from '@/lib/utils';
import { SectionHeading } from './section-heading';
import { AvatarCard } from './staff-card/avatar-card';
import { CaptionedPortraitCard } from './staff-card/captioned-portrait-card';
import { PortraitCard } from './staff-card/portrait-card';
import type { PublicTeamMember } from './types';

const STAFF_CARDS = {
    portrait: PortraitCard,
    'captioned-portrait': CaptionedPortraitCard,
    avatar: AvatarCard,
};

/** Up to this many fit side by side on a phone; more scroll sideways. */
const SIDE_BY_SIDE = 3;

/** "Quién te atiende": the active team, in the template's card. */
export function TeamSection({ team }: { team: PublicTeamMember[] }) {
    const { layout } = useSalonTheme();
    const Card = STAFF_CARDS[layout.staffCard];
    const scrolls = team.length > SIDE_BY_SIDE;

    if (team.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="equipo-titulo"
            className="flex flex-col gap-6"
        >
            <SectionHeading
                id="equipo-titulo"
                eyebrow="El equipo"
                title="Quién te atiende"
            />
            <ul
                className={cn(
                    scrolls
                        ? '-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 lg:mx-0 lg:grid lg:grid-cols-3 lg:gap-5 lg:overflow-visible lg:px-0'
                        : 'grid gap-3 lg:gap-5',
                    !scrolls &&
                        (team.length === SIDE_BY_SIDE
                            ? 'grid-cols-3'
                            : 'grid-cols-2 sm:grid-cols-3'),
                )}
            >
                {team.map((person, index) => (
                    <li
                        // Names may repeat in a team; the order is stable.
                        key={`${person.name}-${index}`}
                        className={cn(
                            scrolls &&
                                'w-[42%] shrink-0 snap-start sm:w-[30%] lg:w-auto',
                        )}
                    >
                        <Card person={person} />
                    </li>
                ))}
            </ul>
        </section>
    );
}
