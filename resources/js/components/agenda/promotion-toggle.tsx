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
 * the visit is then booked at its normal price, which the person has to
 * hear before it is saved.
 */
export function PromotionToggle({ promotion, checked, onChange }: Props) {
    const id = useId();
    const helpId = useId();

    return (
        <div className="rounded-xl border bg-card p-4 text-sm">
            <label
                htmlFor={id}
                className="flex cursor-pointer items-start gap-3"
            >
                <Checkbox
                    id={id}
                    className="mt-0.5 size-5"
                    checked={checked}
                    aria-describedby={helpId}
                    onCheckedChange={(state) => onChange(state === true)}
                />
                <span className="min-w-0 font-medium break-words">
                    Aplicar promoción {promotion.name}{' '}
                    <span className="whitespace-nowrap tabular-nums">
                        (−{formatPrice(promotion.saves)})
                    </span>
                </span>
            </label>
            <p id={helpId} className="mt-1.5 pl-8 text-muted-foreground">
                Si la desmarcas, la cita se cobra al precio normal. Díselo a la
                persona antes de guardar.
            </p>
        </div>
    );
}
