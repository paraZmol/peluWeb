/**
 * Whether a visit came back with an error message. Business rules ("that
 * time was just taken") arrive as a toast after a redirect, which Inertia
 * counts as a success.
 */
export function flashFailed(page: { flash?: unknown }): boolean {
    const flash = page.flash as { toast?: { type?: string } } | undefined;

    return flash?.toast?.type === 'error';
}

/** The text of the message a visit came back with, if any. */
export function flashMessage(page: { flash?: unknown }): string | undefined {
    const flash = page.flash as { toast?: { message?: string } } | undefined;

    return flash?.toast?.message;
}
