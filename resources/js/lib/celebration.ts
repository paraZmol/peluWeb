const storageKey = (code: string) => `peluweb:celebrate:${code}`;

/** Called with the booking that was just made: its page may celebrate once. */
export function markBookingCelebration(booking: unknown): void {
    const code = (booking as { code?: unknown } | null)?.code;

    if (typeof code !== 'string') {
        return;
    }

    try {
        window.sessionStorage.setItem(storageKey(code), '1');
    } catch {
        // Private mode without storage: no confetti, nothing else changes.
    }
}

/** True the first time it is asked after the mark; never again. */
export function takeBookingCelebration(code: string): boolean {
    try {
        const marked = window.sessionStorage.getItem(storageKey(code)) !== null;
        window.sessionStorage.removeItem(storageKey(code));

        return marked;
    } catch {
        return false;
    }
}
