import { Check, Clock } from 'lucide-react';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { formatMinutes, formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BookableService } from './types';

type Props = {
    service: BookableService;
    wizard: BookingWizard;
    disabled: boolean;
};

/** Lowest price it can cost: its cheapest option, else its own price. */
function priceFrom(service: BookableService): string {
    return service.variants.length > 0
        ? service.variants.reduce((min, variant) =>
              Number(variant.price) < Number(min.price) ? variant : min,
          ).price
        : service.price;
}

/** A service to tick, with its options once chosen. */
export function ServiceOption({ service, wizard, disabled }: Props) {
    const selected = wizard.isSelected(service.id);
    const variantId = wizard.selections.find(
        (selection) => selection.serviceId === service.id,
    )?.variantId;

    return (
        <li
            className={cn(
                'rounded-xl transition-colors',
                // Chosen: the brand's tint and edge instead of the card skin.
                selected
                    ? 'border border-brand-line bg-brand-soft shadow-card'
                    : 'skin-card',
            )}
        >
            <button
                type="button"
                aria-pressed={selected}
                disabled={disabled}
                onClick={() => wizard.toggleService(service)}
                className="flex min-h-18 w-full items-center gap-3.5 rounded-xl p-4 text-left outline-none focus-visible:ring-[3px] focus-visible:ring-ring disabled:opacity-50"
            >
                <span
                    aria-hidden
                    className={cn(
                        'flex size-6 shrink-0 items-center justify-center rounded-full transition-colors',
                        selected
                            ? 'skin-primary'
                            : 'border-[1.5px] border-input',
                    )}
                >
                    {selected && <Check className="size-4" strokeWidth={3} />}
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block font-semibold break-words">
                        {service.name}
                        {service.isAddon && (
                            <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground">
                                adicional
                            </span>
                        )}
                    </span>
                    {service.description && (
                        <span className="line-clamp-2 text-sm text-muted-foreground">
                            {service.description}
                        </span>
                    )}
                    <span className="mt-0.5 flex items-center gap-1 text-sm text-muted-foreground tabular-nums">
                        <Clock className="size-3.5" aria-hidden />
                        {formatMinutes(service.minutes)}
                    </span>
                </span>
                <span className="shrink-0 text-right font-bold tabular-nums">
                    {service.variants.length > 0 && (
                        <span className="block text-xs font-normal text-muted-foreground">
                            desde
                        </span>
                    )}
                    {formatPrice(priceFrom(service))}
                </span>
            </button>

            {selected && service.variants.length > 0 && (
                <div
                    role="radiogroup"
                    aria-label={`Opciones de ${service.name}`}
                    className="flex flex-wrap gap-2 px-4 pb-4 sm:pl-14"
                >
                    {service.variants.map((variant) => (
                        <button
                            key={variant.id}
                            type="button"
                            role="radio"
                            aria-checked={variantId === variant.id}
                            onClick={() =>
                                wizard.chooseVariant(service.id, variant.id)
                            }
                            className={cn(
                                'min-h-11 rounded-full px-4 text-sm font-medium tabular-nums outline-none focus-visible:ring-[3px] focus-visible:ring-ring',
                                variantId === variant.id
                                    ? 'skin-primary'
                                    : 'skin-chip',
                            )}
                        >
                            {variant.label} · {formatPrice(variant.price)}
                        </button>
                    ))}
                </div>
            )}
        </li>
    );
}
