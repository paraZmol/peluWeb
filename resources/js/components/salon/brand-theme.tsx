import { createContext, use } from 'react';
import type { CSSProperties, ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { BrandTokens, SalonTheme } from '@/types';
import {
    ALWAYS_DARK_THEMES,
    PLATFORM_THEME,
    SALON_THEMES,
} from './salon-themes';
import type { SalonThemeLayout } from './salon-themes';

type Props = {
    brand: BrandTokens | null;
    /** Design template; without it the platform look (editorial). */
    theme?: SalonTheme;
    className?: string;
    children: ReactNode;
};

type ThemeContext = { theme: SalonTheme; brand: BrandTokens | null };

const SalonThemeContext = createContext<ThemeContext | null>(null);

/**
 * Applies a salon's template and brand color to everything inside:
 * `data-theme` switches the template tokens and fonts (app.css), and the
 * brand variables feed `.brand-scope`, which picks the light or dark variant
 * the server computed (BrandColor). Content rendered in a portal (dialogs,
 * sheets, popovers) must be wrapped again: see `useSalonTheme`.
 */
export function BrandTheme({
    brand,
    theme = PLATFORM_THEME,
    className,
    children,
}: Props) {
    const style = brand
        ? ({
              '--brand-light': brand.light.primary,
              '--brand-light-foreground': brand.light.foreground,
              '--brand-dark': brand.dark.primary,
              '--brand-dark-foreground': brand.dark.foreground,
          } as CSSProperties)
        : undefined;

    return (
        <SalonThemeContext value={{ theme, brand }}>
            <div
                data-theme={theme}
                className={cn(
                    brand && 'brand-scope',
                    ALWAYS_DARK_THEMES.has(theme) && 'dark',
                    'font-sans',
                    className,
                )}
                style={style}
            >
                {children}
            </div>
        </SalonThemeContext>
    );
}

/**
 * The template around a component: its layout variants, plus `theme` and
 * `brand` to re-wrap portal content in `<BrandTheme className="contents">`.
 */
export function useSalonTheme(): ThemeContext & { layout: SalonThemeLayout } {
    const context = use(SalonThemeContext);
    const theme = context?.theme ?? PLATFORM_THEME;

    return {
        theme,
        brand: context?.brand ?? null,
        layout: SALON_THEMES[theme].layout,
    };
}
