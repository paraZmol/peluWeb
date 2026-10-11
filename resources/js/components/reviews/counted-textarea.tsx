import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    label: ReactNode;
    value: string;
    onChange: (value: string) => void;
    maxLength: number;
    error?: string;
    /** A line under the field, beside the counter. */
    hint?: string;
    placeholder?: string;
    isRequired?: boolean;
};

/** Free text with its label, its error and how much room is left. */
export function CountedTextarea({
    id,
    label,
    value,
    onChange,
    maxLength,
    error,
    hint,
    placeholder,
    isRequired = false,
}: Props) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Textarea
                id={id}
                value={value}
                maxLength={maxLength}
                required={isRequired}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={Boolean(error)}
                aria-describedby={cn(
                    hint && `${id}-hint`,
                    `${id}-count`,
                    error && `${id}-error`,
                )}
                className="min-h-28"
            />
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-1">
                    <InputError id={`${id}-error`} message={error} />
                    {hint && (
                        <p
                            id={`${id}-hint`}
                            className="text-xs text-muted-foreground"
                        >
                            {hint}
                        </p>
                    )}
                </div>
                <p
                    id={`${id}-count`}
                    className="ml-auto shrink-0 text-xs text-muted-foreground tabular-nums"
                >
                    {value.length} / {maxLength}
                </p>
            </div>
        </div>
    );
}
