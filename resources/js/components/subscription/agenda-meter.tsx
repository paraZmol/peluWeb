import { cn } from '@/lib/utils';

type Props = {
    withAgenda: number;
    max: number;
    className?: string;
};

/** How many of the plan's agendas are in use, as words and a bar. */
export function AgendaMeter({ withAgenda, max, className }: Props) {
    const share = max > 0 ? Math.min(withAgenda / max, 1) : 1;
    const isFull = withAgenda >= max;

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <p className="text-sm">
                <span className="font-semibold tabular-nums">
                    {withAgenda} de {max}
                </span>{' '}
                {max === 1 ? 'profesional' : 'profesionales'} con agenda
                {isFull && (
                    <span className="text-muted-foreground"> · completo</span>
                )}
            </p>
            <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                aria-hidden
            >
                <div
                    className="h-full origin-left rounded-full bg-primary transition-transform duration-500"
                    style={{ transform: `scaleX(${share})` }}
                />
            </div>
        </div>
    );
}
