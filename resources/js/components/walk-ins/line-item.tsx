import { Button } from '@/components/ui/button';
import { useWalkInMove } from '@/hooks/use-walk-in-move';
import { cn } from '@/lib/utils';
import type { WalkIn } from './types';

/** "Llegó 10:05 · Corte · con Beto": what is known of them, in one line. */
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
    onMoved: (announcement: string) => void;
};

/** One person in the day's line, with what the team does next with them. */
export function LineItem({ walkIn, turn, onMoved }: Props) {
    const { isBusy, move } = useWalkInMove(walkIn, onMoved);
    const isCalled = walkIn.status === 'called';

    return (
        <li
            aria-busy={isBusy}
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
                <p className="min-w-0 font-medium break-words">{walkIn.name}</p>
                {isCalled && (
                    <p className="text-sm font-medium text-brand-text tabular-nums">
                        Llamado{walkIn.calledAt && ` ${walkIn.calledAt}`}
                    </p>
                )}
                <p className="text-sm break-words text-muted-foreground">
                    {details(walkIn)}
                </p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {isCalled ? (
                    <Button
                        variant="outline"
                        className="h-11"
                        disabled={isBusy}
                        aria-label={`Devolver a ${walkIn.name} a la espera`}
                        onClick={() => move('waiting')}
                    >
                        Volver a la espera
                    </Button>
                ) : (
                    <Button
                        className="h-11"
                        disabled={isBusy}
                        aria-label={`Llamar a ${walkIn.name}`}
                        onClick={() => move('called')}
                    >
                        Llamar
                    </Button>
                )}
                <Button
                    variant={isCalled ? 'default' : 'outline'}
                    className="h-11"
                    disabled={isBusy}
                    aria-label={`${walkIn.name}: se atendió`}
                    onClick={() => move('attended')}
                >
                    Se atendió
                </Button>
                <Button
                    variant="ghost"
                    className="ml-auto h-11 sm:ml-4"
                    disabled={isBusy}
                    aria-label={`${walkIn.name}: se fue`}
                    onClick={() => move('left')}
                >
                    Se fue
                </Button>
            </div>
        </li>
    );
}
