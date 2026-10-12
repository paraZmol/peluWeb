/** Chrome's install event; not in the DOM typings. */
export type InstallEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

const DISMISSED_KEY = 'install-dismissed';
const listeners = new Set<() => void>();
let pending: InstallEvent | null = null;

/** Tells whoever shows the install card that the offer came or went. */
function notify() {
    listeners.forEach((listener) => listener());
}

/**
 * Starts the service worker and keeps the browser's install offer for when
 * a screen wants to show it: the event fires once, often before any page
 * component is mounted. Only in built assets: in development the worker
 * would cache modules that change on every save.
 */
export function startPwa() {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        pending = event as InstallEvent;
        notify();
    });
    window.addEventListener('appinstalled', () => {
        pending = null;
        notify();
    });

    if (import.meta.env.PROD && 'serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            void navigator.serviceWorker.register('/sw.js').catch(() => {
                // No worker, no offline copy: the site works the same.
            });
        });
    }
}

export function pendingInstall(): InstallEvent | null {
    return pending;
}

export function clearPendingInstall() {
    pending = null;
    notify();
}

export function onInstallChange(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/** Signing out: the copy of the person's bookings leaves the device. */
export function forgetOfflineCopies() {
    if (typeof navigator !== 'undefined' && 'serviceWorker' in navigator) {
        navigator.serviceWorker.controller?.postMessage('forget');
    }
}

/** Already opened from the home screen. */
export function isInstalled(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

/** iPhone and iPad have no install event: the person adds it by hand. */
export function isIos(userAgent: string, maxTouchPoints = 0): boolean {
    return (
        /iPad|iPhone|iPod/.test(userAgent) ||
        // iPadOS says it is a Mac, with a touch screen.
        (/Macintosh/.test(userAgent) && maxTouchPoints > 1)
    );
}

export function wasInstallDismissed(): boolean {
    try {
        return window.localStorage.getItem(DISMISSED_KEY) === '1';
    } catch {
        return false;
    }
}

export function dismissInstall() {
    try {
        window.localStorage.setItem(DISMISSED_KEY, '1');
    } catch {
        // Private mode: it will be offered again, nothing else to do.
    }
}
