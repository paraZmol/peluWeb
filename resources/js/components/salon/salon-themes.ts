import type { SalonTheme } from '@/types';

/**
 * Layout variants each template uses (spec: .claude/skills/ui-system §4).
 * Colors, fonts, radii and shadows live in app.css under [data-theme]; this
 * only decides which arrangement a shared component renders.
 */
export type SalonThemeLayout = {
    /** Salon page cover. */
    hero: 'full-bleed' | 'card-over-cover' | 'color-block' | 'stacked';
    /** One service in the public list. */
    serviceRow: 'list' | 'photo-card' | 'round-thumb';
    /** One professional in "Quién te atiende" and in the staff step. */
    staffCard: 'portrait' | 'avatar';
    /** How "today", the chosen day and the open status stand out. */
    highlight: 'outline' | 'soft' | 'solid';
};

export type SalonThemeOption = {
    value: SalonTheme;
    label: string;
    description: string;
    layout: SalonThemeLayout;
};

export const SALON_THEMES: Record<SalonTheme, SalonThemeOption> = {
    nocturna: {
        value: 'nocturna',
        label: 'Nocturna',
        description:
            'Fondo oscuro, fotos a todo lo ancho y tu color brillando en los botones.',
        layout: {
            hero: 'full-bleed',
            serviceRow: 'list',
            staffCard: 'portrait',
            highlight: 'outline',
        },
    },
    luminosa: {
        value: 'luminosa',
        label: 'Luminosa',
        description:
            'Blanca y luminosa, con fotos grandes y halos suaves de tu color.',
        layout: {
            hero: 'card-over-cover',
            serviceRow: 'photo-card',
            staffCard: 'avatar',
            highlight: 'soft',
        },
    },
    vibrante: {
        value: 'vibrante',
        label: 'Vibrante',
        description:
            'Tu color en bloques grandes, fotos con formas y un toque gráfico.',
        layout: {
            hero: 'color-block',
            serviceRow: 'round-thumb',
            staffCard: 'portrait',
            highlight: 'solid',
        },
    },
    editorial: {
        value: 'editorial',
        label: 'Editorial',
        description:
            'Clásica y sobria: tonos cálidos y una serif elegante para los títulos.',
        layout: {
            hero: 'stacked',
            serviceRow: 'list',
            staffCard: 'avatar',
            highlight: 'soft',
        },
    },
};

/** Order of the options in "Diseño de tu página". */
export const SALON_THEME_ORDER: SalonTheme[] = [
    'nocturna',
    'luminosa',
    'vibrante',
    'editorial',
];

/**
 * What BrandTheme uses without a `theme` prop (panels, dialogs, payloads
 * without `theme`): editorial is the platform's own look. A salon's default
 * template is decided by the server from its business type.
 */
export const PLATFORM_THEME: SalonTheme = 'editorial';

/**
 * Templates that look the same whatever the visitor's light/dark setting:
 * an owner who picks Nocturna gets a dark page for every client. The other
 * templates follow the visitor's mode.
 */
export const ALWAYS_DARK_THEMES: ReadonlySet<SalonTheme> = new Set([
    'nocturna',
]);
