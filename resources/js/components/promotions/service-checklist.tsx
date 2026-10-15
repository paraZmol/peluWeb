import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { formatMinutes, formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PromotionService } from './types';

type Props = {
    legend: string;
    hint?: string;
    services: PromotionService[];
    /** One service for a single choice (the gift), several otherwise. */
    selected: number[];
    onToggle: (id: number) => void;
    /** Shows the "Todos" shortcut. */
    onPickAll?: () => void;
    /** Single choice: radios instead of checkboxes. */
    single?: boolean;
    /** A service that cannot be picked here, and why. */
    blocked?: { ids: number[]; reason: string };
    name: string;
    error?: string;
};

/** The salon's services as tappable rows; nothing is picked beforehand. */
export function ServiceChecklist({
    legend,
    hint,
    services,
    selected,
    onToggle,
    onPickAll,
    single = false,
    blocked,
    name,
    error,
}: Props) {
    return (
        <div
            role={single ? 'radiogroup' : 'group'}
            aria-labelledby={`${name}-legend`}
            className="grid gap-2"
        >
            <div className="flex items-end justify-between gap-3">
                <p id={`${name}-legend`} className="text-sm font-medium">
                    {legend}
                    {hint && (
                        <span className="block font-normal text-muted-foreground">
                            {hint}
                        </span>
                    )}
                </p>
                {onPickAll && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="-mr-2 shrink-0"
                        onClick={onPickAll}
                    >
                        Todos
                    </Button>
                )}
            </div>
            <ul className="max-h-72 divide-y overflow-y-auto rounded-xl border bg-card">
                {services.map((service) => {
                    const isBlocked =
                        blocked?.ids.includes(service.id) ?? false;

                    return (
                        <li key={service.id}>
                            <label
                                className={cn(
                                    'flex min-h-12 cursor-pointer items-center gap-3 px-3 py-2 has-checked:bg-brand-soft has-focus-visible:ring-2 has-focus-visible:ring-ring has-focus-visible:ring-inset',
                                    isBlocked &&
                                        'cursor-not-allowed opacity-60',
                                )}
                            >
                                <input
                                    type={single ? 'radio' : 'checkbox'}
                                    name={name}
                                    value={service.id}
                                    checked={selected.includes(service.id)}
                                    disabled={isBlocked}
                                    onChange={() => onToggle(service.id)}
                                    className="size-4 shrink-0 accent-primary"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium break-words">
                                        {service.name}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {isBlocked
                                            ? blocked?.reason
                                            : formatMinutes(service.minutes)}
                                    </span>
                                </span>
                                <span className="shrink-0 text-sm tabular-nums">
                                    {formatPrice(service.price)}
                                </span>
                            </label>
                        </li>
                    );
                })}
            </ul>
            <InputError message={error} />
        </div>
    );
}
