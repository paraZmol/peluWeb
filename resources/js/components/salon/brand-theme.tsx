import type { CSSProperties, ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { BrandTokens } from '@/types';

type Props = {
    brand: BrandTokens | null;
    className?: string;
    children: ReactNode;
};

/**
 * Applies a salon's brand color to everything inside. The server already
 * computed readable light/dark variants (BrandColor); `.brand-scope` in
 * app.css picks the right one for the current theme.
 */
export function BrandTheme({ brand, className, children }: Props) {
    if (!brand) {
        return <div className={className}>{children}</div>;
    }

    const style = {
        '--brand-light': brand.light.primary,
        '--brand-light-foreground': brand.light.foreground,
        '--brand-dark': brand.dark.primary,
        '--brand-dark-foreground': brand.dark.foreground,
    } as CSSProperties;

    return (
        <div className={cn('brand-scope', className)} style={style}>
            {children}
        </div>
    );
}
