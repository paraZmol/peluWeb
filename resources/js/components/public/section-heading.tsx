import { cn } from '@/lib/utils';

type Props = {
    id: string;
    title: string;
    /** Short label above the title, e.g. "La carta". */
    eyebrow?: string;
    /** A count on the right: "6 servicios". */
    aside?: string;
    /** In the narrow side column on desktop: one size down. */
    compact?: boolean;
    className?: string;
};

/** Title of a section of the salon page, in the template's display face. */
export function SectionHeading({
    id,
    title,
    eyebrow,
    aside,
    compact = false,
    className,
}: Props) {
    return (
        <div className={cn('flex items-end justify-between gap-4', className)}>
            <div className="flex min-w-0 flex-col gap-2">
                {eyebrow && (
                    <p className="flex items-center gap-2 text-xs font-bold tracking-[.16em] text-brand-text uppercase">
                        <span aria-hidden className="h-0.5 w-5 bg-primary" />
                        {eyebrow}
                    </p>
                )}
                <h2
                    id={id}
                    className={cn(
                        'display-type text-title-2',
                        compact && 'lg:text-title-3',
                    )}
                >
                    {title}
                </h2>
            </div>
            {aside && (
                <p className="shrink-0 pb-1 text-sm text-muted-foreground tabular-nums">
                    {aside}
                </p>
            )}
        </div>
    );
}
