import { Lock } from 'lucide-react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    label: string;
    help: string;
    value: string;
    onChange: (value: string) => void;
    maxLength: number;
    error?: string;
    /** Why it cannot be written yet; set, the field is switched off. */
    lockedReason?: string;
    placeholder?: string;
};

/** One block of free text on the file, with its help and how much is left. */
export function NotesField({
    id,
    label,
    help,
    value,
    onChange,
    maxLength,
    error,
    lockedReason,
    placeholder,
}: Props) {
    const isLocked = lockedReason !== undefined;

    return (
        // "Antes de atender" jumps here: `${id}-field`.
        <div id={`${id}-field`} className="grid scroll-mt-4 gap-2">
            <Label htmlFor={id}>{label}</Label>
            <p id={`${id}-help`} className="text-sm text-muted-foreground">
                {help}
            </p>
            {isLocked && (
                <p
                    id={`${id}-locked`}
                    className="flex items-center gap-2 text-sm"
                >
                    <Lock className="size-4 shrink-0" aria-hidden />
                    {lockedReason}
                </p>
            )}
            <Textarea
                id={id}
                value={value}
                maxLength={maxLength}
                disabled={isLocked}
                placeholder={isLocked ? undefined : placeholder}
                onChange={(event) => onChange(event.target.value)}
                aria-describedby={cn(
                    `${id}-help`,
                    isLocked && `${id}-locked`,
                    `${id}-count`,
                    error && `${id}-error`,
                )}
                aria-invalid={Boolean(error)}
                className="min-h-28"
            />
            <div className="flex items-start justify-between gap-3">
                <InputError id={`${id}-error`} message={error} />
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
