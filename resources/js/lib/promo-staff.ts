import type { BookableService } from '@/components/booking/types';
import type { PublicPromotion } from '@/components/public/types';
import { list } from './promotions';

/*
 | A promotion limited to some professionals (docs/promociones.md, "Filtro
 | por profesional"): it holds only when the client chooses one of them;
 | with "Quien esté disponible" it does not. The server prices it; this only
 | says so before it does.
 */

/** Its professionals' names, in the team's order; empty = all of the team. */
export function staffNamesOf(promotion: PublicPromotion): string[] {
    return (promotion.staff ?? []).map((person) => person.name);
}

/** It holds with this choice of professional (null = whoever is free). */
export function holdsWith(
    promotion: PublicPromotion,
    staffId: number | null,
): boolean {
    const staff = promotion.staff ?? [];

    return (
        staff.length === 0 ||
        (staffId !== null && staff.some((person) => person.id === staffId))
    );
}

/** "Ana", "Ana o Luis", "Ana, Luis o Eva". */
export function eitherOf(names: string[]): string {
    return names.length < 2
        ? (names[0] ?? '')
        : `${names.slice(0, -1).join(', ')} o ${names.at(-1)}`;
}

/** "con Ana o Luis", under a price only they give; null for all of the team. */
export function staffPriceNote(names: string[] = []): string | null {
    return names.length === 0 ? null : `con ${eitherOf(names)}`;
}

/** The limited promotions that touch the chosen services. */
function limitedFor(
    promotions: PublicPromotion[],
    chosenIds: number[],
): PublicPromotion[] {
    return promotions.filter(
        (promotion) =>
            (promotion.staff ?? []).length > 0 &&
            promotion.serviceIds.some((id) => chosenIds.includes(id)),
    );
}

/** Who gets a promotion of the chosen services only if chosen. */
export function staffWithPromo(
    promotions: PublicPromotion[],
    chosenIds: number[],
): Set<number> {
    return new Set(
        limitedFor(promotions, chosenIds).flatMap((promotion) =>
            (promotion.staff ?? []).map((person) => person.id),
        ),
    );
}

/**
 * Before choosing a professional: which promotion is theirs only. Null
 * when none of the chosen services has one limited to someone.
 */
export function staffPromotionHint(
    promotions: PublicPromotion[],
    chosenIds: number[],
): string | null {
    const limited = limitedFor(promotions, chosenIds);

    if (limited.length === 0) {
        return null;
    }

    const [first] = limited;
    const names = staffNamesOf(first);

    return limited.length === 1
        ? `${first.name} vale solo con ${list(names)}: ${names.length === 1 ? 'elige a esa persona' : 'elige a una de estas personas'} para tenerla.`
        : 'Hay promociones que valen solo con ciertas personas del equipo: las marcamos con «con promo».';
}

/**
 * The professional a promotion's link starts with: its only one, when they
 * do every service it starts with. Null otherwise.
 */
export function soleStaffId(
    promotion: PublicPromotion | undefined,
    chosen: BookableService[],
): number | null {
    const staff = promotion?.staff ?? [];

    if (staff.length !== 1 || chosen.length === 0) {
        return null;
    }

    const [person] = staff;

    return chosen.every((service) => service.staffIds.includes(person.id))
        ? person.id
        : null;
}
