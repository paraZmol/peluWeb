/*
| Service worker of the installable app. Hand-written and small on purpose:
| - built assets (hashed names) and icons: cache first;
| - page loads: network first; without connection, "Mis reservas" comes
|   from the copy kept on the last visit and anything else gets the
|   offline page;
| - everything else (form posts, Inertia visits, private pictures) is left
|   to the network untouched.
| Bump VERSION when this file changes what it caches: old caches go away.
*/
const VERSION = 'v1';
const STATIC = `static-${VERSION}`;
// The person's own bookings: wiped on sign-out (see the "forget" message).
const PAGES = `pages-${VERSION}`;
const OFFLINE_URL = '/offline.html';
const OFFLINE_PAGES = /^\/mis-reservas(\/[A-Za-z0-9-]+)?$/;
const STATIC_FILES = /^\/(build[^/]*\/assets|icons|fonts)\/|^\/favicon\.(ico|svg)$|^\/apple-touch-icon\.png$/;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC)
            .then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((names) =>
                Promise.all(
                    names
                        .filter((name) => name !== STATIC && name !== PAGES)
                        .map((name) => caches.delete(name)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    // Signing out: nothing of the person stays on the device.
    if (event.data === 'forget') {
        event.waitUntil(caches.delete(PAGES));
    }
});

async function cacheFirst(request) {
    const cache = await caches.open(STATIC);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        cache.put(request, response.clone());
    }

    return response;
}

async function pageLoad(request, path) {
    try {
        const response = await fetch(request);

        // A redirect to the login page is not a copy of their bookings.
        if (OFFLINE_PAGES.test(path) && response.ok && !response.redirected) {
            const cache = await caches.open(PAGES);
            cache.put(path, response.clone());
        }

        return response;
    } catch {
        const saved = OFFLINE_PAGES.test(path)
            ? await (await caches.open(PAGES)).match(path)
            : undefined;

        return saved ?? (await caches.open(STATIC)).match(OFFLINE_URL);
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (STATIC_FILES.test(url.pathname)) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(pageLoad(request, url.pathname));
    }
});
