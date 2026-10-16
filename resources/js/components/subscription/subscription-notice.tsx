import { Link } from '@inertiajs/react';
import { CircleAlert, Clock, X } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useState } from 'react';
import { useCurrentSalon } from '@/hooks/use-current-salon';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { subscriptionNotice } from '@/lib/subscription';
import { cn } from '@/lib/utils';
import { show as myPlan } from '@/routes/subscription';

/**
 * Over every salon screen: the owner hears about the trial or plan ending,
 * a late payment and a paused salon; the rest of the team only about a
 * pause, because they cannot pay. The server refuses changes either way.
 */
export function SubscriptionNotice() {
    const { currentSalon, isOwner } = useCurrentSalon();
    const { isCurrentUrl } = useCurrentUrl();
    const reduceMotion = useReducedMotion();
    const [dismissedKey, setDismissedKey] = useState<string | null>(null);

    // "Mi plan" already says all of this, at length.
    if (!currentSalon || isCurrentUrl(myPlan())) {
        return null;
    }

    const { state, daysLeft } = currentSalon.subscription;
    const notice = subscriptionNotice({ state, daysLeft, isOwner });
    const key = `${currentSalon.id}:${state}:${daysLeft}`;

    if (notice === null || dismissedKey === key) {
        return null;
    }

    // Warnings can be put aside for this visit; a pause cannot.
    const canDismiss = !notice.isBlocking && state !== 'grace';
    const Icon = notice.isBlocking ? CircleAlert : Clock;

    return (
        <motion.div
            role={notice.isBlocking ? 'alert' : 'status'}
            data-test="subscription-notice"
            initial={{ opacity: 0, y: reduceMotion ? 0 : -8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
            className={cn(
                'mx-4 mt-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm md:mx-6 print:hidden',
                notice.isBlocking
                    ? 'border-destructive/40 bg-destructive/10'
                    : 'border-warning/40 bg-warning/10',
            )}
        >
            <Icon
                className={cn(
                    'mt-0.5 size-4 shrink-0',
                    notice.isBlocking
                        ? 'text-destructive-foreground'
                        : 'text-warning',
                )}
                aria-hidden
            />
            <div className="flex min-w-0 flex-1 flex-col gap-x-3 gap-y-1 sm:flex-row sm:flex-wrap sm:items-baseline">
                <p className="min-w-0">{notice.message}</p>
                {notice.showsPlanLink && (
                    <Link
                        href={myPlan()}
                        className="font-medium whitespace-nowrap text-brand-text underline underline-offset-4"
                    >
                        Ver mi plan
                    </Link>
                )}
            </div>
            {canDismiss && (
                <button
                    type="button"
                    onClick={() => setDismissedKey(key)}
                    aria-label="Ocultar aviso"
                    className="-m-1.5 shrink-0 rounded-md p-1.5 text-muted-foreground hover:bg-foreground/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <X className="size-4" aria-hidden />
                </button>
            )}
        </motion.div>
    );
}
