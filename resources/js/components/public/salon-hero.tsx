import { useSalonTheme } from '@/components/salon/brand-theme';
import { CardOverCoverHero } from './hero/card-over-cover-hero';
import { ColorBlockHero } from './hero/color-block-hero';
import { effectiveHero } from './hero/cover';
import type { HeroProps } from './hero/cover';
import { FullBleedHero } from './hero/full-bleed-hero';
import { StackedHero } from './hero/stacked-hero';

const HEROES = {
    'full-bleed': FullBleedHero,
    'card-over-cover': CardOverCoverHero,
    'color-block': ColorBlockHero,
    stacked: StackedHero,
};

/** The salon's cover in the arrangement of its template (`layout.hero`). */
export function SalonHero({ salon }: HeroProps) {
    const { layout } = useSalonTheme();
    const Hero = HEROES[effectiveHero(layout.hero, salon)];

    return <Hero salon={salon} />;
}
