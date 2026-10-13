import { useId } from 'react';
import type { SlotPromotion } from '@/components/booking/types';
import { Checkbox } from '@/components/ui/checkbox';
import { formatPrice } from '@/lib/format';

type Props = {
    /** The promotion the chosen time gets. */
    promotion: SlotPromotion;
    checked: boolean;
    onChange: (checked: boolean) => void;
};

/**
 * When the team books, the promotion comes ticked and can be taken off:
 * the visit is then booked at its normal price.
 */
export function PromotionToggle({ promotion, checked, onChange }: Props) {
    const id = useId();

    return (
        <label
            htmlFor={id}
            className="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border bg-card p-4 text-sm"
        >
            <Checkbox
                id={id}
                className="mt-0.5 size-5"
                checked={checked}
                onCheckedChange={(state) => onChange(state === true)}
            />
            <span className="min-w-0 font-medium break-words">
                Aplicar promoción {promotion.name}{' '}
                <span className="whitespace-nowrap tabular-nums">
                    (−{formatPrice(promotion.saves)})
                </span>
            </span>
        </label>
    );
}
