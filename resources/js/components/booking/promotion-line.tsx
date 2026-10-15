import { Tag } from 'lucide-react';
import { formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    name: string;
    /** What it takes off, as the server sends it ("7.00"). */
    amount: string;
    className?: string;
};

/** "Promoción {nombre}: −S/ 7.00", wherever a booking shows its money. */
export function PromotionLine({ name, amount, className }: Props) {
    return (
        <p
            className={cn(
                'flex items-start gap-2 text-sm font-semibold text-brand-text',
                className,
            )}
        >
            <Tag className="mt-0.5 size-4 shrink-0" aria-hidden />
            <span className="min-w-0 break-words">
                Promoción {name}:{' '}
                <span className="whitespace-nowrap tabular-nums">
                    −{formatPrice(amount)}
                </span>
            </span>
        </p>
    );
}
