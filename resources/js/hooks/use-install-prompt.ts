import { useEffect, useState, useSyncExternalStore } from 'react';
import {
    clearPendingInstall,
    dismissInstall,
    isInstalled,
    isIos,
    onInstallChange,
    pendingInstall,
    wasInstallDismissed,
} from '@/lib/pwa';

/**
 * Whether to offer installing the app here and how: the browser's own
 * prompt where there is one (Android, desktop), written steps on iPhone
 * and iPad. Nothing once installed or after "Ahora no".
 */
export function useInstallPrompt() {
    const event = useSyncExternalStore(
        onInstallChange,
        pendingInstall,
        () => null,
    );
    // Decided after mounting: the server and the first paint know nothing of the device.
    const [device, setDevice] = useState<'ios' | 'other' | null>(null);
    const [isDismissed, setIsDismissed] = useState(true);

    useEffect(() => {
        setIsDismissed(wasInstallDismissed() || isInstalled());
        setDevice(
            isIos(navigator.userAgent, navigator.maxTouchPoints)
                ? 'ios'
                : 'other',
        );
    }, []);

    const mode: 'prompt' | 'ios' | null = isDismissed
        ? null
        : event !== null
          ? 'prompt'
          : device === 'ios'
            ? 'ios'
            : null;

    return {
        mode,
        install: async () => {
            if (event === null) {
                return;
            }

            await event.prompt();
            await event.userChoice;
            // Used once, whatever they chose.
            clearPendingInstall();
        },
        dismiss: () => {
            dismissInstall();
            setIsDismissed(true);
        },
    };
}
