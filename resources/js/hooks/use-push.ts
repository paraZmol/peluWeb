import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { disablePush, enablePush, pushState } from '@/lib/push';
import type { PushState } from '@/lib/push';

/**
 * Notifications on this device: where they stand and the switch. Null
 * while it is being worked out, or when the server has them off.
 */
export function usePush() {
    const { pushKey } = usePage().props.features;
    const [state, setState] = useState<PushState | null>(null);
    const [isBusy, setIsBusy] = useState(false);
    const [hasFailed, setHasFailed] = useState(false);

    useEffect(() => {
        if (pushKey === null) {
            return;
        }

        let isCurrent = true;
        void pushState().then((found) => {
            if (isCurrent) {
                setState(found);
            }
        });

        return () => {
            isCurrent = false;
        };
    }, [pushKey]);

    const change = async (action: () => Promise<PushState>) => {
        setIsBusy(true);
        setHasFailed(false);

        try {
            setState(await action());
        } catch {
            setHasFailed(true);
        } finally {
            setIsBusy(false);
        }
    };

    /** After unblocking them in the browser: looks again, says what it found. */
    const recheck = async (): Promise<PushState> => {
        setIsBusy(true);
        setHasFailed(false);

        try {
            const found = await pushState();
            setState(found);

            return found;
        } finally {
            setIsBusy(false);
        }
    };

    return {
        state: pushKey === null ? null : state,
        isBusy,
        hasFailed,
        enable: () => change(() => enablePush(pushKey ?? '')),
        disable: () => change(disablePush),
        recheck,
    };
}
