import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { update } from '@/routes/walk-ins';
import type { WalkIn, WalkInStatus } from './types';

function move(walkIn: WalkIn, status: WalkInStatus) {
    router.patch(update(walkIn.id), { status }, { preserveScroll: true });
}

/** "10:05 · Corte · con Beto": what is known of them, in one line. */
function details(walkIn: WalkIn): string {
    return [
        walkIn.arrivedAt ? `Llegó ${walkIn.arrivedAt}` : null,
        walkIn.serviceName,
        walkIn.staffName ? `con ${walkIn.staffName}` : null,
    ]
        .filter(Boolean)
        .join(' · ');
}

type Props = {
    walkIn: WalkIn;
    /** Their place among who is still waiting; null once called. */
    turn: number | null;
};

/** One person in the day's line, with what the team does next with them. */
export function LineItem({ walkIn, turn }: Props) {
    const isCalled = walkIn.status === 'called';

    return (
        <li
            className={cn(
                'flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center',
                isCalled && 'border-primary',
            )}
        >
            <span
                aria-hidden
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold tabular-nums',
                    isCalled
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted',
                )}
            >
                {turn ?? '→'}
            </span>
            <div className="min-w-0 flex-1">
                <p className="font-medium">
                    {walkIn.name}
                    {isCalled && (
                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                            Llamado{walkIn.calledAt && ` ${walkIn.calledAt}`}
                        </span>
                    )}
                </p>
                <p className="text-sm text-muted-foreground">
                    {details(walkIn)}
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                {isCalled ? (
                    <Button
                        variant="outline"
                        className="h-11"
                        aria-label={`Devolver a ${walkIn.name} a la espera`}
                        onClick={() => move(walkIn, 'waiting')}
                    >
                        Volver a la espera
                    </Button>
                ) : (
                    <Button
                        className="h-11"
                        aria-label={`Llamar a ${walkIn.name}`}
                        onClick={() => move(walkIn, 'called')}
                    >
                        Llamar
                    </Button>
                )}
                <Button
                    variant={isCalled ? 'default' : 'outline'}
                    className="h-11"
                    aria-label={`${walkIn.name}: se atendió`}
                    onClick={() => move(walkIn, 'attended')}
                >
                    Se atendió
                </Button>
                <Button
                    variant="ghost"
                    className="h-11"
                    aria-label={`${walkIn.name}: se fue`}
                    onClick={() => move(walkIn, 'left')}
                >
                    Se fue
                </Button>
            </div>
        </li>
    );
}
