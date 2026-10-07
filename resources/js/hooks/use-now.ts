import { useEffect, useState } from 'react';

/**
 * The current instant (ms), refreshed on an interval so countdowns move.
 * Null until mounted: the server render has no "now" the browser would agree with.
 */
export function useNow(intervalMs: number): number | null {
    const [now, setNow] = useState<number | null>(null);

    useEffect(() => {
        setNow(Date.now());
        const timer = window.setInterval(() => setNow(Date.now()), intervalMs);

        return () => window.clearInterval(timer);
    }, [intervalMs]);

    return now;
}
