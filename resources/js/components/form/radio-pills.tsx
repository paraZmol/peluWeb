import { cn } from '@/lib/utils';
import type { Option } from '@/types';

type Props = {
    legend: string;
    name: string;
    options: Option[];
    value: string;
    onChange: (value: string) => void;
};

/** A short, controlled single choice shown as tappable pills. */
export function RadioPills({ legend, name, options, value, onChange }: Props) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-2 text-sm font-medium">{legend}</legend>
            <div className="grid grid-cols-3 gap-2">
                {options.map((option) => (
                    <label key={option.value} className="cursor-pointer">
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            checked={value === option.value}
                            onChange={() => onChange(option.value)}
                            className="peer sr-only"
                        />
                        <span
                            className={cn(
                                'flex min-h-11 items-center justify-center rounded-xl border bg-card px-2 text-center text-sm transition-colors',
                                'peer-checked:border-primary peer-checked:bg-primary/10 peer-focus-visible:ring-2 peer-focus-visible:ring-ring',
                            )}
                        >
                            {option.label}
                        </span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
