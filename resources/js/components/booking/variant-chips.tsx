import { formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BookableVariant } from './types';

type Props = {
    /** Names the group for screen readers: "Opciones de Corte". */
    label: string;
    variants: BookableVariant[];
    value: number | null | undefined;
    onChange: (variantId: number) => void;
    className?: string;
};

/** The options of a service as chips with their price: one is always chosen. */
export function VariantChips({
    label,
    variants,
    value,
    onChange,
    className,
}: Props) {
    return (
        <div
            role="radiogroup"
            aria-label={label}
            className={cn('flex flex-wrap gap-2', className)}
        >
            {variants.map((variant) => (
                <button
                    key={variant.id}
                    type="button"
                    role="radio"
                    aria-checked={value === variant.id}
                    onClick={() => onChange(variant.id)}
                    className={cn(
                        'min-h-11 rounded-full px-4 text-sm font-medium tabular-nums outline-none focus-visible:ring-[3px] focus-visible:ring-ring',
                        value === variant.id ? 'skin-primary' : 'skin-chip',
                    )}
                >
                    {variant.label} · {formatPrice(variant.price)}
                </button>
            ))}
        </div>
    );
}
