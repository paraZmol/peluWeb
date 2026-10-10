import { useId } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    /** One line that says what the figures mean. */
    description?: string;
    children: ReactNode;
    className?: string;
};

/**
 * One block of the report: a titled card that is never cut in two when the
 * report is printed.
 */
export function ReportSection({
    title,
    description,
    children,
    className,
}: Props) {
    const titleId = useId();

    return (
        <section
            aria-labelledby={titleId}
            className={cn(
                'flex min-w-0 break-inside-avoid flex-col gap-4 rounded-xl border bg-card p-5 shadow-card',
                className,
            )}
        >
            <div>
                <h2 id={titleId} className="text-lg font-semibold">
                    {title}
                </h2>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

/** What a block says when the period has nothing for it. */
export function SectionEmpty({ children }: { children: ReactNode }) {
    return <p className="text-sm text-muted-foreground">{children}</p>;
}
