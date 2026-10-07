import { Check, Clock } from 'lucide-react';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { MAX_SERVICES } from '@/hooks/use-booking-wizard';
import { formatMinutes, formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BookableSalon, BookableService } from './types';

type Props = { salon: BookableSalon; wizard: BookingWizard };

export function StepServices({ salon, wizard }: Props) {
    const isFull = wizard.selections.length >= MAX_SERVICES;

    if (wizard.services.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                Este salón todavía no tiene servicios para reservar en línea.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            {salon.serviceGroups.map((group) => (
                <section key={group.label} className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                        {group.label}
                    </h2>
                    <ul className="flex flex-col gap-2">
                        {group.services.map((service) => (
                            <ServiceOption
                                key={service.id}
                                service={service}
                                wizard={wizard}
                                disabled={
                                    isFull && !wizard.isSelected(service.id)
                                }
                            />
                        ))}
                    </ul>
                </section>
            ))}
            {wizard.hasOnlyAddons && (
                <p role="status" className="text-sm text-muted-foreground">
                    Los adicionales se reservan junto con otro servicio.
                </p>
            )}
        </div>
    );
}

type OptionProps = {
    service: BookableService;
    wizard: BookingWizard;
    disabled: boolean;
};

function ServiceOption({ service, wizard, disabled }: OptionProps) {
    const selected = wizard.isSelected(service.id);
    const variantId = wizard.selections.find(
        (s) => s.serviceId === service.id,
    )?.variantId;

    return (
        <li
            className={cn(
                'rounded-xl border bg-card transition-colors',
                selected && 'border-primary bg-primary/5',
            )}
        >
            <button
                type="button"
                aria-pressed={selected}
                disabled={disabled}
                onClick={() => wizard.toggleService(service)}
                className="flex min-h-16 w-full items-center gap-3 rounded-xl p-4 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
            >
                <span
                    aria-hidden
                    className={cn(
                        'flex size-6 shrink-0 items-center justify-center rounded-full border transition-colors',
                        selected &&
                            'border-primary bg-primary text-primary-foreground',
                    )}
                >
                    {selected && <Check className="size-4" />}
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block font-medium break-words">
                        {service.name}
                        {service.isAddon && (
                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                adicional
                            </span>
                        )}
                    </span>
                    <span className="flex items-center gap-1 text-sm text-muted-foreground tabular-nums">
                        <Clock className="size-3.5" aria-hidden />
                        {formatMinutes(service.minutes)}
                    </span>
                </span>
                <span className="shrink-0 text-right font-medium tabular-nums">
                    {service.variants.length > 0 && (
                        <span className="block text-xs font-normal text-muted-foreground">
                            desde
                        </span>
                    )}
                    {formatPrice(
                        service.variants.length > 0
                            ? service.variants.reduce((min, v) =>
                                  Number(v.price) < Number(min.price) ? v : min,
                              ).price
                            : service.price,
                    )}
                </span>
            </button>

            {selected && service.variants.length > 0 && (
                <div
                    role="radiogroup"
                    aria-label={`Opciones de ${service.name}`}
                    className="flex flex-wrap gap-2 px-4 pb-4"
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
                                'min-h-11 rounded-full border px-4 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                variantId === variant.id
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'bg-background hover:bg-accent',
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
