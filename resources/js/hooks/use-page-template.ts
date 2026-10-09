import { setLayoutProps } from '@inertiajs/react';
import { useEffect, useSyncExternalStore } from 'react';
import type { BrandTokens, SalonTheme } from '@/types';

/** The template and brand color of the one salon a whole page belongs to. */
export type PageTemplate = { theme: SalonTheme; brand: BrandTokens | null };

let active: PageTemplate | null = null;
const listeners = new Set<() => void>();

function publish(template: PageTemplate | null): void {
    active = template;
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/**
 * Declares that the whole page is one salon's (its public page, the booking
 * wizard, one booking): what lives outside the page then wears that salon's
 * template too. The app shell gets it as a layout prop, from the first
 * paint; the toasts, which render outside the Inertia app, read the store.
 */
export function usePageTemplate({ theme, brand }: PageTemplate): void {
    setLayoutProps({ template: { theme, brand } });

    const light = brand?.light;
    const dark = brand?.dark;

    useEffect(() => {
        publish({ theme, brand });

        return () => publish(null);
        // The colors, not the object: props are rebuilt on every visit.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        theme,
        light?.primary,
        light?.foreground,
        dark?.primary,
        dark?.foreground,
    ]);
}

/** The template of the page on screen; null on pages of the platform. */
export function useActivePageTemplate(): PageTemplate | null {
    return useSyncExternalStore(
        subscribe,
        () => active,
        () => null,
    );
}
