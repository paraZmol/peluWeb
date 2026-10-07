import { cn } from '@/lib/utils';

const monthFormat = new Intl.DateTimeFormat('es-PE', { month: 'short' });

type Props = {
    /** "Y-m-d" on the salon's clock. */
    date: string;
    className?: string;
};

/**
 * A small calendar leaf (month over day). Decorative: the date is always
 * written out next to it.
 */
export function DateTile({ date, className }: Props) {
    // Built from parts so the browser's timezone never shifts the day.
    const [year, month, day] = date.split('-').map(Number);

    return (
        <span
            aria-hidden
            className={cn(
                'flex size-12 shrink-0 flex-col items-center justify-center rounded-xl border bg-background',
                className,
            )}
        >
            <span className="text-xs leading-4 text-muted-foreground uppercase">
                {monthFormat
                    .format(new Date(year, month - 1, day))
                    .replace('.', '')}
            </span>
            <span className="text-lg leading-5 font-semibold tabular-nums">
                {day}
            </span>
        </span>
    );
}
