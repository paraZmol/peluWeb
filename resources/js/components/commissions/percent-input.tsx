import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const MAX_DIGITS = 3;

type Props = Omit<ComponentProps<'input'>, 'value' | 'onChange' | 'type'> & {
    /** A whole percent as text ("40"): the server checks it is 0-100. */
    value: string;
    onChange: (percent: string) => void;
};

/** A field for a whole percent with the numeric keypad on a phone. */
export function PercentInput({ value, onChange, className, ...props }: Props) {
    return (
        <div className="relative">
            <Input
                {...props}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                maxLength={MAX_DIGITS}
                placeholder="0"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                onFocus={(event) => event.target.select()}
                className={cn('h-11 pr-9 tabular-nums', className)}
            />
            <span
                aria-hidden
                className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
            >
                %
            </span>
        </div>
    );
}
