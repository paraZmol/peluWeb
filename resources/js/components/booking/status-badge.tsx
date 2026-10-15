import { cn } from '@/lib/utils';
import type { BookingStatus } from './types';

const STYLES: Record<BookingStatus, string> = {
    pending: 'bg-warning/10 text-warning',
    confirmed: 'bg-success/10 text-success',
    in_service: 'bg-brand-soft text-brand-text',
    completed: 'bg-muted text-muted-foreground',
    cancelled: 'bg-muted text-muted-foreground line-through',
    no_show: 'bg-destructive/10 text-destructive-foreground',
    expired: 'bg-muted text-muted-foreground',
};

type Props = { status: BookingStatus; label: string; className?: string };

/** The label always says the status; color only reinforces it. */
export function StatusBadge({ status, label, className }: Props) {
    return (
        <span
            className={cn(
                'inline-flex w-fit items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                STYLES[status],
                className,
            )}
        >
            {label}
        </span>
    );
}
