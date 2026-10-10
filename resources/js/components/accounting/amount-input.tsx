import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Props = Omit<ComponentProps<'input'>, 'value' | 'onChange' | 'type'> & {
    /** Soles as text ("20.50"): never parsed here. */
    value: string;
    onChange: (amount: string) => void;
};

/** A field for soles with the numeric keypad on a phone. */
export function AmountInput({ value, onChange, className, ...props }: Props) {
    return (
        <div className="relative">
            <span
                aria-hidden
                className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground"
            >
                S/
            </span>
            <Input
                {...props}
                type="text"
                inputMode="decimal"
                autoComplete="off"
                placeholder="0.00"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                onFocus={(event) => event.target.select()}
                className={cn('h-11 pl-9 tabular-nums', className)}
            />
        </div>
    );
}
