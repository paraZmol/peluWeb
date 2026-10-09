import { useEffect, useRef } from 'react';
import { useSalonTheme } from './brand-theme';

/**
 * A full-page BrandTheme only paints its own box: the scroll bounce, the
 * phone's safe areas and the window's scrollbar belong to the document
 * behind it, which wears the platform's colors (light under an always-dark
 * template). Rendered inside BrandTheme, this lends the template's
 * background and color scheme to the document while the page is mounted,
 * and follows the visitor's light/dark switch.
 */
export function ThemeBackdrop() {
    const { theme } = useSalonTheme();
    const probe = useRef<HTMLSpanElement>(null);

    useEffect(() => {
        const root = document.documentElement;
        const sync = () => {
            if (!probe.current) {
                return;
            }

            const { backgroundColor, colorScheme } = getComputedStyle(
                probe.current,
            );

            // Only real changes: writing `style` wakes the observer again.
            if (root.style.backgroundColor !== backgroundColor) {
                root.style.backgroundColor = backgroundColor;
            }

            if (root.style.colorScheme !== colorScheme) {
                root.style.colorScheme = colorScheme;
            }
        };

        sync();
        // The appearance setting flips a class on <html> and its color scheme.
        const observer = new MutationObserver(sync);
        observer.observe(root, { attributeFilter: ['class', 'style'] });

        return () => {
            observer.disconnect();
            root.style.backgroundColor = '';
            // Back to what the appearance setting keeps there (use-appearance).
            root.style.colorScheme = root.classList.contains('dark')
                ? 'dark'
                : 'light';
        };
    }, [theme]);

    return <span ref={probe} aria-hidden className="hidden bg-background" />;
}
