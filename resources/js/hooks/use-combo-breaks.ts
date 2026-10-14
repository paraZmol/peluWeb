import { useState } from 'react';
import type { BookableService } from '@/components/booking/types';
import type { PublicPromotion } from '@/components/public/types';
import { comboBreaks, wholeCombos } from '@/lib/promo-offers';
import type { ComboBreak } from '@/lib/promo-offers';

/**
 * "Sin Barba el combo no aplica": said once a combo the person had whole
 * loses one of its services, and forgotten when none of it is left.
 */
export function useComboBreaks(
    promotions: PublicPromotion[],
    services: BookableService[],
    chosenIds: number[],
): ComboBreak[] {
    const [wholeIds, setWholeIds] = useState<number[]>([]);
    const stillChosen = wholeIds.filter((id) =>
        promotions
            .find((promotion) => promotion.id === id)
            ?.serviceIds.some((serviceId) => chosenIds.includes(serviceId)),
    );
    const remembered = [
        ...new Set([...stillChosen, ...wholeCombos(promotions, chosenIds)]),
    ];

    // Adjusted while rendering, as React suggests for state that follows props.
    if (
        remembered.length !== wholeIds.length ||
        remembered.some((id) => !wholeIds.includes(id))
    ) {
        setWholeIds(remembered);
    }

    return comboBreaks(
        promotions,
        chosenIds,
        remembered,
        (id) => services.find((service) => service.id === id)?.name,
    );
}
