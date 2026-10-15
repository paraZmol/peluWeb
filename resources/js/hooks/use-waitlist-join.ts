import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { Selection } from '@/components/booking/types';
import { flashFailed, flashMessage } from '@/lib/flash';
import {
    ALREADY_WAITING,
    NETWORK_PROBLEM,
    THERE_ARE_TIMES,
} from '@/lib/waitlist';
import { store } from '@/routes/salons/waitlist';

type Wanted = {
    selections: Selection[];
    /** null = whoever is free. */
    staffId: number | null;
    /** "Y-m-d": the day with no free times. */
    date: string;
};

type Options = {
    salonSlug: string;
    /** The day turned out to have times: ask for them again. */
    onStale: () => void;
};

/**
 * Joining a salon's waiting list from the wizard. Remembers the days joined
 * in this visit; the server is who knows the whole list.
 */
export function useWaitlistJoin({ salonSlug, onStale }: Options) {
    const [sending, setSending] = useState(false);
    const [problem, setProblem] = useState<string>();
    const [joined, setJoined] = useState<string[]>([]);

    const join = ({ selections, staffId, date }: Wanted) =>
        router.post(
            store(salonSlug),
            {
                services: selections.map((selection) => ({
                    id: selection.serviceId,
                    variant: selection.variantId,
                })),
                staff: staffId,
                date,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => {
                    setSending(true);
                    setProblem(undefined);
                },
                onFinish: () => setSending(false),
                onSuccess: (page) => {
                    const message = flashMessage(page) ?? '';

                    // A refusal arrives as a toast, which already says why.
                    if (
                        !flashFailed(page) ||
                        message.includes(ALREADY_WAITING)
                    ) {
                        return setJoined((days) => [...days, date]);
                    }

                    if (message.includes(THERE_ARE_TIMES)) {
                        onStale();
                    }
                },
                onError: (errors) => setProblem(Object.values(errors)[0]),
                onNetworkError: () => setProblem(NETWORK_PROBLEM),
            },
        );

    return {
        sending,
        problem,
        hasJoined: (date: string) => joined.includes(date),
        join,
    };
}
