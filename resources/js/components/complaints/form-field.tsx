import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    className?: string;
    children: ReactNode;
};

/** Label, control, hint and error of one field of the complaint sheet. */
export function FormField({
    id,
    label,
    error,
    hint,
    className,
    children,
}: Props) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && (
                <p id={`${id}-hint`} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}
