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

/** Who the wizard talks to: the person booking, or the team for them. */
export type CompanionVoice = 'client' | 'staff';

export const COMPANION_COPY: Record<
    CompanionVoice,
    {
        question: string;
        hint: string;
        variantFor: string;
        missing: string;
    }
> = {
    client: {
        question: '¿Cómo se llama quien te acompaña?',
        hint: 'Solo el nombre o como prefiera que lo llamen. Lo verán el salón y tú, solo para esta cita. Avísale que lo compartes.',
        variantFor: 'quien te acompaña',
        missing: 'Falta el nombre de quien te acompaña',
    },
    staff: {
        question: '¿Cómo se llama quien viene con la persona?',
        hint: 'Solo el nombre o como prefiera que lo llamen. Se guarda solo para esta cita.',
        variantFor: 'quien viene con la persona',
        missing: 'Falta el nombre de quien viene con la persona',
    },
};

/** Both are attended by the same professional, in either voice. */
export const SAME_STAFF_NOTE =
    'Les atiende la misma persona, uno después del otro.';

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

const COMPANION_LINE = /^(.+) · para (.+)$/;

/**
 * A booking's services in one line for the team. Two lines of the same
 * service, the second for who comes along ("Corte", "Corte · para Lucía"),
 * read as one: "Corte ×2 (también para Lucía)", not as a repeated line.
 */
export function servicesLine(services: string[]): string {
    const merged = services.flatMap((service) => {
        const base = COMPANION_LINE.exec(service)?.[1];

        if (base && services.includes(base)) {
            return [];
        }

        const companionLine = services
            .map((other) => COMPANION_LINE.exec(other))
            .find((match) => match?.[1] === service);

        return companionLine
            ? [`${service} ×2 (también para ${companionLine[2]})`]
            : [service];
    });

    return merged.join(', ');
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
