import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = { label: string; children: ReactNode; className?: string };

/**
 * One amount under its own label, inside a `dl`: reads the same on a phone
 * and on a desk, with no table header to lose.
 */
export function CashFigure({ label, children, className }: Props) {
    return (
        <div className={cn('min-w-0 sm:text-right', className)}>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium tabular-nums">{children}</dd>
        </div>
    );
}
