import { NETWORK_PROBLEM } from '@/lib/reviews';

/** The request never arrived: what was written is still there to retry. */
export function NetworkProblem({ isShown }: { isShown: boolean }) {
    if (!isShown) {
        return null;
    }

    return (
        <p role="alert" className="text-sm text-destructive-foreground">
            {NETWORK_PROBLEM}
        </p>
    );
}
