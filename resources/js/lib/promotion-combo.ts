import type { PromotionService } from '@/components/promotions/types';
import { toCents } from './money';

/*
 | A combo as the owner fills it in (docs/promociones.md, "Combo a precio
 | cerrado"): the same limits App\Concerns\ComboPromotionRules checks.
 */

export const COMBO_MIN_SERVICES = 2;

export const COMBO_MAX_SERVICES = 3;

const MIN_PERCENT_OFF = 5;

const MAX_PERCENT_OFF = 50;

/** The price proposed: about this much less, in whole soles. */
const SUGGESTED_PERCENT_OFF = 10;

/** What a service is measured at in a combo: its cheapest option. */
export function comboBaseCents(service: PromotionService): number {
    return toCents(service.basePrice ?? service.price) ?? 0;
}

/** What can go in a combo: no evaluation first. */
export function comboCandidates(
    services: PromotionService[],
): PromotionService[] {
    return services.filter((service) => !service.requiresEvaluation);
}

/** The services one after the other, each at its base price. */
export function comboNormalCents(services: PromotionService[]): number {
    return services.reduce(
        (total, service) => total + comboBaseCents(service),
        0,
    );
}

/** The prices it may be set at: from 50 % to 5 % less, like the server. */
export function comboRange(normalCents: number): {
    lowest: number;
    highest: number;
} {
    return {
        lowest: Math.floor((normalCents * (100 - MAX_PERCENT_OFF)) / 100),
        highest: Math.floor((normalCents * (100 - MIN_PERCENT_OFF)) / 100),
    };
}

/** About 10 % less, down to whole soles, and always within the range. */
export function suggestedComboCents(normalCents: number): number {
    const { lowest, highest } = comboRange(normalCents);
    const rounded =
        Math.floor((normalCents * (100 - SUGGESTED_PERCENT_OFF)) / 10000) * 100;

    return Math.min(highest, Math.max(lowest, rounded));
}

/** Its price may change with an option: it is announced "desde". */
export function comboIsFrom(services: PromotionService[]): boolean {
    return services.some((service) => service.hasOptions === true);
}

/** Enough services, of the kind that fits: an add-on alone is no combo. */
export function isComboComplete(services: PromotionService[]): boolean {
    return (
        services.length >= COMBO_MIN_SERVICES &&
        services.length <= COMBO_MAX_SERVICES &&
        services.some((service) => !service.isAddon)
    );
}
