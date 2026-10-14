import type { Selection } from '@/components/booking/types';
import type { PublicPromotion } from '@/components/public/types';
import { pairFor } from './promo-offers';

/*
 | "Ven con alguien" in the wizard (docs/promociones.md): one service chosen
 | that a pair promotion takes, and someone who comes along for the same
 | one. The server prices it; this only builds the second line.
 */

/** Only their name: no phone, no account. */
export const MAX_COMPANION_NAME = 60;

export type Companion = {
    serviceId: number;
    /** As typed; trimmed when it is sent. */
    name: string;
    /** Their own option of the service, when it has options. */
    variantId: number | null;
};

/** The promotion that takes the selection for two: exactly one service. */
export function pairOffer(
    selections: Selection[],
    promotions: PublicPromotion[],
): PublicPromotion | undefined {
    return selections.length === 1
        ? pairFor(promotions, selections[0].serviceId)
        : undefined;
}

/**
 * What is asked for and booked: the selection, and the line of who comes
 * along once they have a name (without one the server refuses the repeat).
 */
export function bookedLines(
    selections: Selection[],
    companion: Companion | null,
): Selection[] {
    const name = companion?.name.trim() ?? '';

    return companion && name !== ''
        ? [
              ...selections,
              {
                  serviceId: companion.serviceId,
                  variantId: companion.variantId,
                  companion: name,
              },
          ]
        : selections;
}

/**
 * Who comes along, while the selection is still that one service: picked
 * through a "Ven con alguien", or repeating a visit for two (then at
 * whatever price the server quotes). Anything else drops them.
 */
export function keptCompanion(
    selections: Selection[],
    companion: Companion | null,
): Companion | null {
    return selections.length === 1 &&
        selections[0].serviceId === companion?.serviceId
        ? companion
        : null;
}

/** What is booked, by name: "Corte · para Lucía" for who comes along. */
export function withCompanion(
    chosen: { name: string }[],
    companion: Companion | null,
): { name: string }[] {
    const names = chosen.map(({ name }) => ({ name }));

    return companion && chosen.length > 0
        ? [
              ...names,
              { name: `${chosen[0].name} · para ${companion.name.trim()}` },
          ]
        : names;
}
