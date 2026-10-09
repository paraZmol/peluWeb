import type { CSSProperties } from 'react';
import { cn } from '@/lib/utils';
import type { BrandTokens, SalonTheme } from '@/types';
import { useSalonTheme } from './brand-theme';
import { ALWAYS_DARK_THEMES } from './salon-themes';

type PortalThemeProps = {
    'data-theme': SalonTheme;
    className: string;
    style: CSSProperties | undefined;
};

/**
 * What BrandTheme puts on its container, as props for an element that
 * lives outside it: template, brand and forced dark mode.
 */
export function templateProps(
    theme: SalonTheme,
    brand: BrandTokens | null,
): PortalThemeProps {
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

/**
 * Dialogs and sheets render in `body`, outside the page's BrandTheme, so
 * they would fall back to the platform's look (and, under a template that
 * is always dark, to light surfaces). React context does cross portals:
 * spread these props on the portal's own container (`DialogContent`) and it
 * wears the page's template, brand and forced dark mode — its background,
 * border and close button included. Merge `className` with `cn`.
 */
export function usePortalTheme(): PortalThemeProps {
    const { brand, theme } = useSalonTheme();

    return templateProps(theme, brand);
}
