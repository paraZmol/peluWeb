/**
 * Whether a visit came back with an error message. Business rules ("that
 * time was just taken") arrive as a toast after a redirect, which Inertia
 * counts as a success.
 */
export function flashFailed(page: { flash?: unknown }): boolean {
    const flash = page.flash as { toast?: { type?: string } } | undefined;

    return flash?.toast?.type === 'error';
}
