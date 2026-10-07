import { cn } from '@/lib/utils';
import type { AccessLevel, AccessOption } from './types';

type Props = {
    options: AccessOption[];
    value: AccessLevel;
    onChange: (value: AccessLevel) => void;
};

/** Who can book inside a working range: everyone, VIP clients or the team. */
export function AccessPicker({ options, value, onChange }: Props) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-2 text-sm font-medium">
                ¿Quién puede reservar en este horario?
            </legend>
            {options.map((option) => (
                <label key={option.value} className="cursor-pointer">
                    <input
                        type="radio"
                        name="access-level"
                        value={option.value}
                        checked={value === option.value}
                        onChange={() => onChange(option.value)}
                        className="peer sr-only"
                    />
                    <span
                        className={cn(
                            'flex min-h-11 flex-col justify-center rounded-xl border bg-card px-4 py-2 transition-colors',
                            'peer-checked:border-primary peer-checked:bg-primary/10 peer-focus-visible:ring-2 peer-focus-visible:ring-ring',
                        )}
                    >
                        <span className="text-sm font-medium">
                            {option.label}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {option.hint}
                        </span>
                    </span>
                </label>
            ))}
        </fieldset>
    );
}
