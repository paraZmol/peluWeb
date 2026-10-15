import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { WalkIn, WalkInStatus } from '@/components/walk-ins/types';
import { update } from '@/routes/walk-ins';

/** What a screen reader hears once the move went through. */
const SAID: Record<WalkInStatus, (name: string) => string> = {
    waiting: (name) => `${name} volvió a la fila`,
    called: (name) => `Llamaste a ${name}`,
    attended: (name) => `${name} ya se atendió`,
    left: (name) => `${name} se fue`,
};

/**
 * Moves one person of the line to another status. The row stays busy while
 * the server answers; a refusal comes back as the server's error toast, so
 * the announcement is only made when the new line shows the change.
 */
export function useWalkInMove(
    walkIn: WalkIn,
    onMoved: (announcement: string) => void,
) {
    const [isBusy, setIsBusy] = useState(false);

    const move = (status: WalkInStatus) =>
        router.patch(
            update(walkIn.id),
            { status },
            {
                preserveScroll: true,
                onStart: () => setIsBusy(true),
                onFinish: () => setIsBusy(false),
                onSuccess: (page) => {
                    const line = page.props.line as WalkIn[] | undefined;
                    const moved = line?.find(({ id }) => id === walkIn.id);

                    if (moved?.status === status) {
                        onMoved(SAID[status](walkIn.name));
                    }
                },
            },
        );

    return { isBusy, move };
}
