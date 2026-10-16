import { Link } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { show as myPlan } from '@/routes/subscription';
import type { AgendaLimit } from './types';

/**
 * How many agendas the plan has in use (only those who attend count) and,
 * once every one is taken, what to do about it, right where the count is.
 */
export function AgendaLimitSummary({ limit }: { limit: AgendaLimit }) {
    const isFull = limit.withAgenda >= limit.max;

    return (
        <div className="flex flex-col gap-2">
            <p
                data-test="agenda-limit"
                className="text-sm text-muted-foreground"
            >
                <span className="font-medium text-foreground tabular-nums">
                    {limit.withAgenda} de {limit.max}
                </span>{' '}
                con agenda · Plan {limit.planName}
            </p>
            {isFull && <AgendaFullHint />}
        </div>
    );
}

/** Every agenda of the plan is taken: the way out, one tap away. */
function AgendaFullHint() {
    return (
        <p
            role="status"
            className="flex items-start gap-2.5 rounded-lg bg-brand-soft px-3 py-2.5 text-sm text-pretty"
        >
            <CircleAlert
                className="mt-0.5 size-4 shrink-0 text-brand-text"
                strokeWidth={1.75}
                aria-hidden
            />
            <span>
                Tu plan está completo. Quita la agenda a alguien o cambia de
                plan en{' '}
                <Link
                    href={myPlan()}
                    className="font-medium text-brand-text underline underline-offset-4"
                >
                    «Mi plan»
                </Link>
                .
            </span>
        </p>
    );
}
