import { TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = { children: ReactNode; className?: string };

/** Something the team must read before going on: icon and tint, not only color. */
export function WarningNote({ children, className }: Props) {
    return (
        <p
            role="status"
            className={cn(
                'flex items-start gap-2 rounded-lg bg-warning/10 p-3 text-sm',
                className,
            )}
        >
            <TriangleAlert
                className="mt-0.5 size-4 shrink-0 text-warning"
                aria-hidden
            />
            <span className="min-w-0">{children}</span>
        </p>
    );
}
