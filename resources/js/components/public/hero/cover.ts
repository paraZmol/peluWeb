import type { SalonThemeLayout } from '@/components/salon/salon-themes';
import type { PublicSalon } from '../types';

export type HeroProps = { salon: PublicSalon };

/** The cover, else the first photo of the gallery (plantillas.md §6). */
export function coverPhoto(salon: PublicSalon): string | null {
    return salon.coverUrl ?? salon.photos[0]?.url ?? null;
}

/**
 * The cover a salon actually gets: the photo covers need a photo; without
 * any, they fall back to the stacked one (a soft block of its color).
 */
export function effectiveHero(
    hero: SalonThemeLayout['hero'],
    salon: PublicSalon,
): SalonThemeLayout['hero'] {
    const needsPhoto = hero === 'full-bleed' || hero === 'card-over-cover';

    return needsPhoto && coverPhoto(salon) === null ? 'stacked' : hero;
}

/** "Barbería · Hombres": what the salon is and for whom. */
export function salonKind(salon: PublicSalon): string {
    return `${salon.businessType} · ${salon.clientele}`;
}
