import { Link } from '@inertiajs/react';
import { show as myPlan } from '@/routes/subscription';
import type { AgendaLimit } from './types';

/** How many agendas the plan has in use: only those who attend count. */
export function AgendaLimitSummary({ limit }: { limit: AgendaLimit }) {
    return (
        <p data-test="agenda-limit" className="text-sm text-muted-foreground">
            <span className="font-medium text-foreground tabular-nums">
                {limit.withAgenda} de {limit.max}
            </span>{' '}
            con agenda · Plan {limit.planName}
        </p>
    );
}

/** Under the add button once every agenda of the plan is taken. */
export function AgendaFullHint() {
    return (
        <p className="max-w-xs text-sm text-muted-foreground sm:text-right">
            Tu plan está completo. Quita la agenda a alguien o cambia de plan en{' '}
            <Link
                href={myPlan()}
                className="font-medium text-brand-text underline underline-offset-4"
            >
                «Mi plan»
            </Link>
            .
        </p>
    );
}
