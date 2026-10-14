import type {
    BookableService,
    BookingPromotion,
    Quote,
    Selection,
    Slot,
    SlotPromotion,
} from '@/components/booking/types';
import type { PublicPromotion } from '@/components/public/types';
import { sumAmounts } from './format';
import { fromCents, toCents } from './money';
import { depositCents } from './promotions';

/*
 | What a visitor is told about the salon's promotions before the server
 | prices the booking. Nothing here decides a price: a free time brings the
 | promotion it really gets (`Slot.promotion`), and the server applies it.
 */

const cents = (amount: string): number => toCents(amount) ?? 0;

/** What a service costs normally and with the best promotion that lowers it. */
export type ServiceOffer = {
    normal: string;
    promo: string;
    isFrom: boolean;
    /** The promotion holds only on some days or hours: not every time gets it. */
    isLimited: boolean;
};

/** Goes with a price that only some times of the week get. */
export const LIMITED_PRICE_NOTE = 'en horarios con promo';

/**
 * The lowest promotional price of each service. A service that only earns
 * a gift keeps its price, so it is not here.
 */
export function bestOffers(
    promotions: PublicPromotion[],
): Map<number, ServiceOffer> {
    const offers = new Map<number, ServiceOffer>();

    const priced = promotions.flatMap((promotion) =>
        promotion.prices.map((price) => ({
            ...price,
            isLimited: promotion.when !== null,
        })),
    );

    for (const { serviceId, ...offer } of priced) {
        const current = offers.get(serviceId);
        const lowers = cents(offer.promo) < cents(offer.normal);

        if (lowers && (!current || cents(offer.promo) < cents(current.promo))) {
            offers.set(serviceId, offer);
        }
    }

    return offers;
}

export type GiftOffer = {
    promotionId: number;
    /** The chosen service that earns it. */
    giverId: number;
    serviceId: number;
    name: string;
    price: string;
    minutes: number;
};

/** Every gift the chosen services earn, the most valuable first. */
function earnedGifts(
    promotions: PublicPromotion[],
    chosenIds: number[],
): GiftOffer[] {
    return promotions
        .flatMap((promotion) => {
            const giverId = promotion.serviceIds.find((id) =>
                chosenIds.includes(id),
            );

            return promotion.gift && giverId !== undefined
                ? [{ promotionId: promotion.id, giverId, ...promotion.gift }]
                : [];
        })
        .sort((a, b) => cents(b.price) - cents(a.price));
}

/**
 * The gift already in the selection: its service and one that earns it are
 * both chosen. One gift per booking; null once what earned it is removed.
 */
export function claimedGift(
    promotions: PublicPromotion[],
    chosenIds: number[],
): GiftOffer | null {
    return (
        earnedGifts(promotions, chosenIds).find((gift) =>
            chosenIds.includes(gift.serviceId),
        ) ?? null
    );
}

/** The gift to offer: earned by what was chosen and not added yet. */
export function giftToOffer(
    promotions: PublicPromotion[],
    chosenIds: number[],
): GiftOffer | null {
    return claimedGift(promotions, chosenIds)
        ? null
        : (earnedGifts(promotions, chosenIds)[0] ?? null);
}

/**
 * What the selection could save at most, in céntimos, for the running
 * "desde". One promotion per booking: the best one. A discount counts once,
 * on the service it lowers the most (an amount off is given once per
 * booking), so this never promises more than the server gives.
 */
export function bestSavingCents(
    promotions: PublicPromotion[],
    chosenIds: number[],
): number {
    const savings = promotions.map((promotion) => {
        if (promotion.gift) {
            return claimedGift([promotion], chosenIds)
                ? cents(promotion.gift.price)
                : 0;
        }

        return Math.max(
            0,
            ...promotion.prices
                .filter((price) => chosenIds.includes(price.serviceId))
                .map((price) => cents(price.normal) - cents(price.promo)),
        );
    });

    return Math.max(0, ...savings);
}

/**
 * What the wizard starts with when it is opened from a promotion. A gift:
 * the first service that earns it and the gift. A discount: its service,
 * only when it has just one (several would mean booking them all).
 */
export function promotionSelections(
    promotion: PublicPromotion | undefined,
    services: BookableService[],
): Selection[] {
    if (!promotion) {
        return [];
    }

    const included = services.filter((service) =>
        promotion.serviceIds.includes(service.id),
    );
    const gift = services.find(
        (service) => service.id === promotion.gift?.serviceId,
    );
    const picked = promotion.gift
        ? gift && included.length > 0
            ? [included[0], gift]
            : []
        : included.length === 1
          ? included
          : [];

    return picked.map((service) => ({
        serviceId: service.id,
        variantId: service.variants[0]?.id ?? null,
    }));
}

/**
 * What to know before picking a day and a time, when a promotion of the
 * chosen services holds only on some: which ones, and how they are marked.
 * Null when none of them is limited.
 */
export function limitedPromotionHint(
    promotions: PublicPromotion[],
    chosenIds: number[],
): string | null {
    const limited = promotions.filter(
        (promotion) =>
            promotion.when !== null &&
            promotion.serviceIds.some((id) => chosenIds.includes(id)),
    );

    if (limited.length === 0) {
        return null;
    }

    const [first] = limited;

    return limited.length === 1
        ? `${first.name} vale solo ${first.when}. Los horarios que la tienen dicen «con promo».`
        : 'Hay promociones que valen solo algunos días u horas. Los horarios que las tienen dicen «con promo».';
}

/** The promotion a link asks the wizard to start from ("?promo=12"). */
export function askedPromotion(
    search: string,
    promotions: PublicPromotion[],
): PublicPromotion | undefined {
    const asked = Number(new URLSearchParams(search).get('promo'));

    return promotions.find((promotion) => promotion.id === asked);
}

/** The promotion of a booking as its summary shows it. */
export type SummaryPromotion = {
    /** The price without it, to show crossed out; null without a promotion. */
    listTotal: string | null;
    promotion: { name: string; amount: string } | null;
};

export type PromotedTotal = SummaryPromotion & {
    total: string;
    deposit: string;
};

/** What a booking already made says about the promotion that priced it. */
export function bookedPromotion(
    price: string,
    promotion: BookingPromotion | null,
): SummaryPromotion {
    return promotion
        ? {
              listTotal: sumAmounts([price, promotion.saved]),
              promotion: { name: promotion.name, amount: promotion.saved },
          }
        : { listTotal: null, promotion: null };
}

/**
 * Total and deposit of a booking at a start with a promotion: the quoted
 * price minus what it saves, and the salon's percentage of that (rounded
 * like the server does).
 */
export function promotedTotal(
    quote: Quote,
    promotion: SlotPromotion | null | undefined,
): PromotedTotal {
    if (!promotion) {
        return {
            total: quote.price,
            deposit: quote.deposit,
            listTotal: null,
            promotion: null,
        };
    }

    const totalCents = Math.max(0, cents(quote.price) - cents(promotion.saves));

    return {
        total: fromCents(totalCents),
        deposit: fromCents(depositCents(totalCents, quote.depositPercent)),
        listTotal: quote.price,
        promotion: { name: promotion.name, amount: promotion.saves },
    };
}

/**
 * The line above a day's times, when some of them carry a promotion; null
 * when none does.
 */
export function slotsPromotionNote(slots: Slot[]): string | null {
    const marked = slots.flatMap((slot) =>
        slot.promotion ? [slot.promotion] : [],
    );

    if (marked.length === 0) {
        return null;
    }

    const [first] = marked;
    const isOne = marked.every(
        (promotion) =>
            promotion.name === first.name && promotion.saves === first.saves,
    );
    const which = marked.length === slots.length ? 'de este día' : 'marcados';

    return isOne
        ? `Los horarios ${which} tienen ${first.name}: ahorras S/ ${first.saves}.`
        : `Los horarios ${which} tienen promoción: verás cuánto ahorras al elegir uno.`;
}
