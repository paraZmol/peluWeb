import { Check, Clock } from 'lucide-react';
import type { ReactNode } from 'react';
import { PromoTag } from '@/components/public/promo-tag';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { formatMinutes } from '@/lib/format';
import { offerLabel } from '@/lib/promo-offers';
import type { ServiceOffer } from '@/lib/promo-offers';
import { cn } from '@/lib/utils';
import { OptionPrice } from './option-price';
import type { BookableService } from './types';
import { VariantChips } from './variant-chips';

type Props = {
    service: BookableService;
    wizard: BookingWizard;
    disabled: boolean;
    /** Its price with the salon's best promotion, when one lowers it. */
    offer?: ServiceOffer;
    /** Chosen as the gift of a promotion. */
    isGift?: boolean;
    /** Under the service once chosen: the gift it earns. */
    children?: ReactNode;
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
export function ServiceOption({
    service,
    wizard,
    disabled,
    offer,
    isGift = false,
    children,
}: Props) {
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
                        {(offer || isGift) && (
                            <PromoTag
                                label={
                                    isGift
                                        ? 'Regalo'
                                        : offer && offerLabel(offer)
                                }
                                className="ml-2"
                            />
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
                <OptionPrice
                    price={priceFrom(service)}
                    hasOptions={service.variants.length > 0}
                    offer={offer}
                    isGift={isGift}
                />
            </button>

            {selected && service.variants.length > 0 && (
                <VariantChips
                    label={`Opciones de ${service.name}`}
                    variants={service.variants}
                    value={variantId}
                    onChange={(id) => wizard.chooseVariant(service.id, id)}
                    className="px-4 pb-4 sm:pl-14"
                />
            )}
            {children}
        </li>
    );
}
