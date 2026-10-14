import { Info } from 'lucide-react';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { MAX_SERVICES } from '@/hooks/use-booking-wizard';
import { useComboBreaks } from '@/hooks/use-combo-breaks';
import { bestOffers, claimedGift, giftToOffer } from '@/lib/promo-offers';
import { GiftOffer } from './gift-offer';
import { ServiceOption } from './service-option';
import type { BookableSalon } from './types';

type Props = {
    salon: BookableSalon;
    wizard: BookingWizard;
    /** What to say when there is nothing to choose from. */
    empty?: string;
};

export function StepServices({
    salon,
    wizard,
    empty = 'Este salón todavía no tiene servicios para reservar en línea.',
}: Props) {
    const isFull = wizard.selections.length >= MAX_SERVICES;
    const chosenIds = wizard.chosen.map((service) => service.id);
    const offers = bestOffers(wizard.promotions);
    const claimed = claimedGift(wizard.promotions, chosenIds);
    const breaks = useComboBreaks(
        wizard.promotions,
        wizard.services,
        chosenIds,
    );
    // Only a gift that can be booked here is worth offering.
    const offered = [giftToOffer(wizard.promotions, chosenIds)].find((gift) =>
        wizard.services.some((service) => service.id === gift?.serviceId),
    );
    const gift = claimed ?? offered ?? null;
    const addGift = () => {
        const service = wizard.services.find((s) => s.id === gift?.serviceId);

        if (service) {
            wizard.toggleService(service);
        }
    };

    if (wizard.services.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                {empty}
            </p>
        );
    }

    return (
        // Two columns only where the column itself is wide (public desktop).
        <div className="@container flex flex-col gap-7">
            {salon.serviceGroups.map((group) => (
                <section key={group.label} className="flex flex-col gap-3">
                    <h2 className="font-sans text-xs font-bold tracking-[.16em] text-muted-foreground uppercase">
                        {group.label}
                    </h2>
                    <ul className="grid items-start gap-2.5 @3xl:grid-cols-2">
                        {group.services.map((service) => {
                            const comboBreak = breaks.find(
                                (one) => one.serviceId === service.id,
                            );

                            return (
                                <ServiceOption
                                    key={service.id}
                                    service={service}
                                    wizard={wizard}
                                    disabled={
                                        isFull && !wizard.isSelected(service.id)
                                    }
                                    offer={offers.get(service.id)}
                                    isGift={claimed?.serviceId === service.id}
                                >
                                    {gift?.giverId === service.id && (
                                        <GiftOffer
                                            gift={gift}
                                            isClaimed={claimed !== null}
                                            onAdd={isFull ? null : addGift}
                                        />
                                    )}
                                    {comboBreak && (
                                        <p
                                            role="status"
                                            className="mx-4 mb-4 flex items-start gap-2 text-sm font-semibold text-brand-text"
                                        >
                                            <Info
                                                className="mt-0.5 size-4 shrink-0"
                                                aria-hidden
                                            />
                                            {comboBreak.text}
                                        </p>
                                    )}
                                </ServiceOption>
                            );
                        })}
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
