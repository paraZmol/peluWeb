import { useId } from 'react';
import type { Option } from '@/types';

type Props = {
    /** The question the chips answer, e.g. "¿Cómo paga?". */
    legend: string;
    methods: Option[];
    value: string;
    onChange: (method: string) => void;
};

/**
 * One payment method out of a few. Native radios underneath: arrows move
 * between them and screen readers announce the group.
 */
export function PaymentMethodChips({
    legend,
    methods,
    value,
    onChange,
}: Props) {
    const name = useId();

    return (
        <fieldset className="min-w-0">
            <legend className="mb-2 text-sm font-medium">{legend}</legend>
            <div className="grid grid-cols-2 gap-2 min-[390px]:grid-cols-3">
                {methods.map((method) => (
                    <label
                        key={method.value}
                        className="relative block cursor-pointer"
                    >
                        <input
                            type="radio"
                            name={name}
                            value={method.value}
                            checked={method.value === value}
                            onChange={() => onChange(method.value)}
                            className="peer sr-only"
                        />
                        <span className="flex min-h-11 items-center justify-center rounded-full border px-2 text-center text-sm font-medium transition-colors peer-checked:border-primary peer-checked:bg-brand-soft peer-checked:ring-1 peer-checked:ring-primary peer-checked:ring-inset peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 hover:bg-accent">
                            {method.label}
                        </span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
