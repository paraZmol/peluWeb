import { Tag } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = { label?: string; className?: string };

/** The small mark of a service that is cheaper, or free, with a promotion. */
export function PromoTag({ label = 'Promo', className }: Props) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full bg-brand-soft px-2 py-0.5 align-middle text-xs font-bold tracking-wide whitespace-nowrap text-brand-text uppercase',
                className,
            )}
        >
            <Tag className="size-3" aria-hidden />
            {label}
        </span>
    );
}
