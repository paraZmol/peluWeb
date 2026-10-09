import { useEffect, useSyncExternalStore } from 'react';

function neverChanges(): () => void {
    return () => {};
}

function isInsideFrame(): boolean {
    return window.self !== window.top;
}

function getServerSnapshot(): boolean {
    return false;
}

function blockLinks(event: MouseEvent): void {
    if (event.target instanceof Element && event.target.closest('a[href]')) {
        event.preventDefault();
        event.stopPropagation();
    }
}

/**
 * True when the page is the template preview shown inside "Mi salón" (an
 * iframe of this same site). While framed, links do nothing: following one
 * would open the booking wizard, or a site that refuses to be framed, inside
 * the phone of that dialog. Scrolling and the rest of the page still work.
 */
export function useFramedPreview(isPreview: boolean): boolean {
    const isFramed =
        useSyncExternalStore(neverChanges, isInsideFrame, getServerSnapshot) &&
        isPreview;

    useEffect(() => {
        if (!isFramed) {
            return;
        }

        // Capture phase on the document: runs before React's own handlers.
        document.addEventListener('click', blockLinks, true);

        return () => document.removeEventListener('click', blockLinks, true);
    }, [isFramed]);

    return isFramed;
}
