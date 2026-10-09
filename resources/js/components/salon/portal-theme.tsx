import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';
import type { SalonTheme } from '@/types';
import { useSalonTheme } from './brand-theme';
import { ALWAYS_DARK_THEMES } from './salon-themes';

type PortalThemeProps = {
    'data-theme': SalonTheme;
    className: string;
    style: CSSProperties | undefined;
};

/**
 * Dialogs and sheets render in `body`, outside the page's BrandTheme, so
 * they would fall back to the platform's look (and, under a template that
 * is always dark, to light surfaces). React context does cross portals:
 * spread these props on the portal's own container (`DialogContent`) and it
 * wears the page's template, brand and forced dark mode — its background,
 * border and close button included. Mirrors what BrandTheme puts on its
 * container; merge `className` with `cn`.
 */
export function usePortalTheme(): PortalThemeProps {
    const { brand, theme } = useSalonTheme();

    return {
        'data-theme': theme,
        className: cn(
            brand && 'brand-scope',
            ALWAYS_DARK_THEMES.has(theme) && 'dark',
            'font-sans',
        ),
        style: brand
            ? ({
                  '--brand-light': brand.light.primary,
                  '--brand-light-foreground': brand.light.foreground,
                  '--brand-dark': brand.dark.primary,
                  '--brand-dark-foreground': brand.dark.foreground,
              } as CSSProperties)
            : undefined,
    };
}
