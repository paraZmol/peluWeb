import { cn } from '@/lib/utils';

const monthFormat = new Intl.DateTimeFormat('es-PE', { month: 'short' });

type Props = {
    /** "Y-m-d" on the salon's clock. */
    date: string;
    className?: string;
};

/**
 * A small calendar leaf (month over day) in the brand's tint. Decorative:
 * the date is always written out next to it.
 */
export function DateTile({ date, className }: Props) {
    // Built from parts so the browser's timezone never shifts the day.
    const [year, month, day] = date.split('-').map(Number);

    return (
        <span
            aria-hidden
            className={cn(
                'flex size-13 shrink-0 flex-col items-center justify-center rounded-lg bg-brand-soft',
                className,
            )}
        >
            <span className="text-[0.6875rem] leading-4 font-bold tracking-wider text-brand-text uppercase">
                {monthFormat
                    .format(new Date(year, month - 1, day))
                    .replace('.', '')}
            </span>
            <span className="text-xl leading-6 font-extrabold tabular-nums">
                {day}
            </span>
        </span>
    );
}
