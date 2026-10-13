import { Check, Gift } from 'lucide-react';
import { formatMinutes, formatPrice } from '@/lib/format';
import type { GiftOffer as Offer } from '@/lib/promo-offers';

type Props = {
    gift: Offer;
    /** Already in the selection: it only says so. */
    isClaimed: boolean;
    /** Null when it cannot be added (the selection is full). */
    onAdd: (() => void) | null;
};

/**
 * Under the service that earns it: "Te regalamos …". One tap adds it; never
 * by itself, because it makes the visit longer.
 */
export function GiftOffer({ gift, isClaimed, onAdd }: Props) {
    const extra = `(+${formatMinutes(gift.minutes)})`;

    if (isClaimed) {
        return (
            <p
                role="status"
                className="mx-4 mb-4 flex items-start gap-2 text-sm font-semibold text-brand-text"
            >
                <Check className="mt-0.5 size-4 shrink-0" aria-hidden />
                <span className="min-w-0 break-words">
                    Tu regalo: {gift.name}{' '}
                    <span className="whitespace-nowrap tabular-nums">
                        {extra}
                    </span>
                </span>
            </p>
        );
    }

    return (
        <div className="mx-4 mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-card p-3 shadow-card">
            <span
                aria-hidden
                className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-text"
            >
                <Gift className="size-5" />
            </span>
            <p className="min-w-40 flex-1 text-sm">
                <strong className="block text-base break-words">
                    Te regalamos {gift.name}{' '}
                    <span className="whitespace-nowrap tabular-nums">
                        {extra}
                    </span>
                </strong>
                <span className="text-muted-foreground tabular-nums">
                    Ahorras {formatPrice(gift.price)}
                </span>
            </p>
            {onAdd && (
                <button
                    type="button"
                    onClick={onAdd}
                    className="inline-flex h-11 shrink-0 items-center justify-center rounded-full skin-primary px-5 text-sm font-semibold transition-transform duration-150 outline-none focus-visible:ring-[3px] focus-visible:ring-ring active:scale-[0.97] motion-reduce:transition-none"
                >
                    Agregar gratis
                </button>
            )}
        </div>
    );
}
