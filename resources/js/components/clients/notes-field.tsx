import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    id: string;
    label: string;
    help: string;
    value: string;
    onChange: (value: string) => void;
    maxLength: number;
    error?: string;
    disabled?: boolean;
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
    disabled = false,
    placeholder,
}: Props) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <p id={`${id}-help`} className="text-sm text-muted-foreground">
                {help}
            </p>
            <Textarea
                id={id}
                value={value}
                maxLength={maxLength}
                disabled={disabled}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                aria-describedby={`${id}-help ${id}-count`}
                aria-invalid={Boolean(error)}
                className="min-h-28"
            />
            <div className="flex items-start justify-between gap-3">
                <InputError message={error} />
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
