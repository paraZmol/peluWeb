import { destroy, store } from '@/routes/push';
import { isInstalled, isIos } from './pwa';

/**
 * Where this device stands with notifications:
 * - "unsupported": the browser cannot, or the app has no worker here;
 * - "needs-install": an iPhone or iPad only allows them from the home screen;
 * - "denied": the person blocked them in the browser, only they can undo it;
 * - "off" / "on": can be switched here.
 */
export type PushState =
    | 'unsupported'
    | 'needs-install'
    | 'denied'
    | 'off'
    | 'on';

/** What the push service wants the public key as. */
export function keyToBytes(base64Url: string): Uint8Array<ArrayBuffer> {
    const padded = base64Url.padEnd(
        base64Url.length + ((4 - (base64Url.length % 4)) % 4),
        '=',
    );
    const raw = atob(padded.replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));

    for (let index = 0; index < raw.length; index += 1) {
        bytes[index] = raw.charCodeAt(index);
    }

    return bytes;
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function call(
    route: { url: string; method: string },
    body: unknown,
): Promise<void> {
    const response = await fetch(route.url, {
        method: route.method.toUpperCase(),
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`push ${response.status}`);
    }
}

async function registration(): Promise<ServiceWorkerRegistration | null> {
    if (
        !('serviceWorker' in navigator) ||
        !('PushManager' in window) ||
        !('Notification' in window)
    ) {
        return null;
    }

    return (await navigator.serviceWorker.getRegistration()) ?? null;
}

export async function pushState(): Promise<PushState> {
    const worker = await registration();

    if (worker === null) {
        return isIos(navigator.userAgent, navigator.maxTouchPoints) &&
            !isInstalled()
            ? 'needs-install'
            : 'unsupported';
    }

    if (Notification.permission === 'denied') {
        return 'denied';
    }

    const subscription = await worker.pushManager.getSubscription();

    if (subscription === null) {
        return 'off';
    }

    // On for the browser is not on for this account: someone else may have
    // switched it on here. Saying so again hands the device to who is signed in.
    try {
        await call(store(), subscription.toJSON());
    } catch {
        return 'off';
    }

    return 'on';
}

/** Asks the person, subscribes the device and tells the server. */
export async function enablePush(publicKey: string): Promise<PushState> {
    const worker = await registration();

    if (worker === null) {
        return 'unsupported';
    }

    if ((await Notification.requestPermission()) !== 'granted') {
        return Notification.permission === 'denied' ? 'denied' : 'off';
    }

    const subscription = await worker.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: keyToBytes(publicKey),
    });

    try {
        await call(store(), subscription.toJSON());
    } catch (error) {
        // The server does not know this device: do not leave it half on.
        await subscription.unsubscribe();

        throw error;
    }

    return 'on';
}

/**
 * Signing out: this device stops getting the person's notifications. Never
 * holds the sign-out back: whatever fails here, the push service reports
 * the device as gone on the next send and the server forgets it.
 */
export function releasePush() {
    void disablePush().catch(() => {});
}

export async function disablePush(): Promise<PushState> {
    const subscription =
        (await (await registration())?.pushManager.getSubscription()) ?? null;

    if (subscription !== null) {
        // The browser first: even if the server cannot be told, nothing arrives.
        await subscription.unsubscribe();
        await call(destroy(), { endpoint: subscription.endpoint });
    }

    return 'off';
}
