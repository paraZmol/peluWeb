import { describe, expect, it } from 'vite-plus/test';
import type { BookableService, Quote } from '@/components/booking/types';
import type { PublicPromotion } from '@/components/public/types';
import {
    askedPromotion,
    bestOffers,
    bestSavingCents,
    bookedPromotion,
    claimedGift,
    comboBreaks,
    giftToOffer,
    limitedPromotionHint,
    promotedTotal,
    promotionSelections,
    slotPromoMark,
    slotsPromotionNote,
    wholeCombos,
} from './promo-offers';

const CUT = 1;
const BEARD = 2;
const WASH = 3;
const COLOR = 4;

function promo(with_: Partial<PublicPromotion>): PublicPromotion {
    return {
        id: 10,
        name: '25 % menos en Corte',
        overline: null,
        mechanic: 'discount',
        summary: null,
        endsOn: '2026-10-31',
        period: 'del 5 al 31 de octubre de 2026',
        quota: null,
        when: null,
        finePrint: '',
        serviceIds: [CUT],
        prices: [
            {
                serviceId: CUT,
                name: 'Servicio',
                normal: '40.00',
                promo: '30.00',
                isFrom: false,
            },
        ],
        gift: null,
        combo: null,
        ...with_,
    };
}

const discount = promo({});
const gift = promo({
    id: 11,
    name: 'Lavado gratis con tu Barba',
    mechanic: 'gift',
    serviceIds: [BEARD],
    prices: [
        {
            serviceId: BEARD,
            name: 'Servicio',
            normal: '20.00',
            promo: '20.00',
            isFrom: false,
        },
    ],
    gift: { serviceId: WASH, name: 'Lavado', price: '10.00', minutes: 10 },
});

const combo = promo({
    id: 12,
    name: 'Corte + Barba por S/ 50',
    mechanic: 'combo',
    serviceIds: [CUT, BEARD],
    prices: [
        {
            serviceId: CUT,
            name: 'Corte',
            normal: '40.00',
            promo: '33.30',
            isFrom: false,
        },
        {
            serviceId: BEARD,
            name: 'Barba',
            normal: '20.00',
            promo: '16.70',
            isFrom: false,
        },
    ],
    combo: { price: '50.00', normal: '60.00', saves: '10.00', isFrom: false },
});

function service(id: number, variantIds: number[] = []): BookableService {
    return {
        id,
        name: `Servicio ${id}`,
        description: null,
        minutes: 30,
        price: '40.00',
        isAddon: false,
        requiresEvaluation: false,
        staffIds: [1],
        variants: variantIds.map((variantId) => ({
            id: variantId,
            label: `Opción ${variantId}`,
            price: '40.00',
            extraMinutes: 0,
        })),
    };
}

const quote: Quote = {
    price: '40.00',
    priceVaries: false,
    minutes: 30,
    minutesVary: false,
    depositPercent: 30,
    deposit: '12.00',
};

describe('bestOffers', () => {
    it('keeps the lowest price when two promotions lower the same service', () => {
        const deeper = promo({
            id: 12,
            prices: [
                {
                    serviceId: CUT,
                    name: 'Servicio',
                    normal: '40.00',
                    promo: '28.00',
                    isFrom: false,
                },
            ],
        });

        expect(bestOffers([discount, deeper]).get(CUT)?.promo).toBe('28.00');
        expect(bestOffers([deeper, discount]).get(CUT)?.promo).toBe('28.00');
    });

    it('leaves out a service that only earns a gift', () => {
        const offers = bestOffers([discount, gift]);

        expect(offers.has(BEARD)).toBe(false);
        expect(offers.get(CUT)).toMatchObject({
            normal: '40.00',
            promo: '30.00',
            isFrom: false,
            isLimited: false,
        });
    });

    it('says when the price holds only on some days or hours', () => {
        const quiet = promo({ when: 'de 09:00 a 18:00' });

        expect(bestOffers([quiet]).get(CUT)?.isLimited).toBe(true);
    });
});

describe('limitedPromotionHint', () => {
    const quiet = promo({ when: 'de 09:00 a 18:00' });

    it('says nothing when no promotion of the chosen services is limited', () => {
        expect(limitedPromotionHint([discount, gift], [CUT, BEARD])).toBeNull();
        expect(limitedPromotionHint([quiet], [BEARD])).toBeNull();
    });

    it('names the promotion, when it holds and how its times are marked', () => {
        expect(limitedPromotionHint([quiet, gift], [CUT])).toBe(
            '25 % menos en Corte vale solo de 9:00 a. m. a 6:00 p. m. Los horarios que la tienen dicen «con promo».',
        );
    });

    it('does not name one when several are limited', () => {
        const other = promo({ id: 14, when: 'lunes' });

        expect(limitedPromotionHint([quiet, other], [CUT])).toBe(
            'Hay promociones que valen solo algunos días u horas. Los horarios que las tienen dicen «con promo».',
        );
    });
});

describe('the gift of a promotion', () => {
    it('is offered once a service that earns it is chosen', () => {
        expect(giftToOffer([discount, gift], [CUT])).toBeNull();
        expect(giftToOffer([discount, gift], [BEARD])).toMatchObject({
            giverId: BEARD,
            serviceId: WASH,
            minutes: 10,
        });
    });

    it('stops being offered, and counts as free, once it is added', () => {
        expect(giftToOffer([gift], [BEARD, WASH])).toBeNull();
        expect(claimedGift([gift], [BEARD, WASH])?.serviceId).toBe(WASH);
    });

    it('is no longer free when what earned it is removed', () => {
        expect(claimedGift([gift], [WASH])).toBeNull();
        expect(giftToOffer([gift], [WASH])).toBeNull();
    });

    it('offers the most valuable one when two are earned', () => {
        const bigger = promo({
            id: 13,
            mechanic: 'gift',
            serviceIds: [BEARD],
            prices: [],
            gift: {
                serviceId: COLOR,
                name: 'Cejas',
                price: '15.00',
                minutes: 15,
            },
        });

        expect(giftToOffer([gift, bigger], [BEARD])?.serviceId).toBe(COLOR);
        // One gift per booking: with one taken, the other is not offered.
        expect(giftToOffer([gift, bigger], [BEARD, WASH])).toBeNull();
    });
});

describe('bestSavingCents', () => {
    it('counts one promotion, the one that saves the most', () => {
        expect(bestSavingCents([discount, gift], [CUT])).toBe(1000);
        expect(bestSavingCents([discount, gift], [BEARD])).toBe(0);
        expect(bestSavingCents([discount, gift], [BEARD, WASH])).toBe(1000);
        expect(bestSavingCents([discount, gift], [CUT, BEARD, WASH])).toBe(
            1000,
        );
        expect(bestSavingCents([], [CUT])).toBe(0);
    });

    it('counts a discount once, on the service it lowers the most', () => {
        const both = promo({
            serviceIds: [CUT, BEARD],
            prices: [
                {
                    serviceId: CUT,
                    name: 'Servicio',
                    normal: '40.00',
                    promo: '30.00',
                    isFrom: false,
                },
                {
                    serviceId: BEARD,
                    name: 'Servicio',
                    normal: '20.00',
                    promo: '15.00',
                    isFrom: false,
                },
            ],
        });

        expect(bestSavingCents([both], [CUT, BEARD])).toBe(1000);
    });
});

describe('promotionSelections', () => {
    const services = [service(CUT), service(BEARD, [7, 8]), service(WASH)];

    it('ticks the only service of a discount', () => {
        expect(promotionSelections(discount, services)).toEqual([
            { serviceId: CUT, variantId: null },
        ]);
    });

    it('ticks nothing when a discount covers several services', () => {
        const wide = promo({ serviceIds: [CUT, BEARD] });

        expect(promotionSelections(wide, services)).toEqual([]);
    });

    it('ticks the service that earns a gift and the gift', () => {
        expect(promotionSelections(gift, services)).toEqual([
            { serviceId: BEARD, variantId: 7 },
            { serviceId: WASH, variantId: null },
        ]);
    });

    it('ticks nothing without a promotion or without its services', () => {
        expect(promotionSelections(undefined, services)).toEqual([]);
        expect(promotionSelections(gift, [service(BEARD)])).toEqual([]);
    });
});

describe('askedPromotion', () => {
    it('finds the promotion of the link among the ones running', () => {
        expect(
            askedPromotion('?promo=11&fecha=2026-10-08', [discount, gift]),
        ).toBe(gift);
        expect(askedPromotion('?promo=99', [discount, gift])).toBeUndefined();
        expect(askedPromotion('?servicio=1', [discount])).toBeUndefined();
        expect(askedPromotion('', [discount])).toBeUndefined();
    });
});

describe('promotedTotal', () => {
    it('leaves the quote as it is without a promotion', () => {
        expect(promotedTotal(quote, null)).toEqual({
            total: '40.00',
            deposit: '12.00',
            listTotal: null,
            promotion: null,
        });
    });

    it('takes the saving off and asks the deposit on what is left', () => {
        expect(
            promotedTotal(quote, { name: '25 % menos', saves: '10.00' }),
        ).toEqual({
            total: '30.00',
            deposit: '9.00',
            listTotal: '40.00',
            promotion: { name: '25 % menos', amount: '10.00' },
        });
    });

    it('rounds the deposit to the céntimo like the server', () => {
        const odd = { ...quote, price: '35.00', depositPercent: 15 };

        // 28.50 × 15 % = 4.275 → 4.28
        expect(promotedTotal(odd, { name: 'x', saves: '6.50' }).deposit).toBe(
            '4.28',
        );
    });

    it('never goes below zero', () => {
        expect(promotedTotal(quote, { name: 'x', saves: '50.00' }).total).toBe(
            '0.00',
        );
    });
});

describe('bookedPromotion', () => {
    it('gives back the price before the promotion', () => {
        expect(
            bookedPromotion('28.00', { name: '20 % menos', saved: '7.00' }),
        ).toEqual({
            listTotal: '35.00',
            promotion: { name: '20 % menos', amount: '7.00' },
        });
        expect(bookedPromotion('35.00', null)).toEqual({
            listTotal: null,
            promotion: null,
        });
    });
});

describe('slotsPromotionNote', () => {
    const plain = { start: 1, time: '09:00', promotion: null };
    const marked = {
        start: 2,
        time: '10:00',
        promotion: { name: 'Horas flojas', saves: '7.00' },
    };

    it('says nothing when no time has a promotion', () => {
        expect(
            slotsPromotionNote([plain, { start: 3, time: '09:30' }]),
        ).toBeNull();
        expect(slotsPromotionNote([])).toBeNull();
    });

    it('explains the mark when only some times have it', () => {
        expect(slotsPromotionNote([plain, marked])).toBe(
            'Los horarios marcados tienen Horas flojas: ahorras S/ 7.00.',
        );
    });

    it('says so when every time of the day has it', () => {
        expect(slotsPromotionNote([marked])).toBe(
            'Los horarios de este día tienen Horas flojas: ahorras S/ 7.00.',
        );
    });

    it('does not name an amount when the times save different ones', () => {
        const other = {
            start: 4,
            time: '11:00',
            promotion: { name: 'Horas flojas', saves: '5.00' },
        };

        expect(slotsPromotionNote([plain, marked, other])).toBe(
            'Los horarios marcados tienen promoción: verás cuánto ahorras al elegir uno.',
        );
    });
});

describe('a combo', () => {
    it('marks its services without a price of their own: alone they cost the normal one', () => {
        const offers = bestOffers([combo]);

        expect(offers.get(BEARD)).toEqual({
            normal: '20.00',
            promo: '20.00',
            isFrom: false,
            isLimited: false,
            isCombo: true,
        });
        // A discount that lowers the service is the price to show.
        expect(bestOffers([combo, discount]).get(CUT)?.isCombo).toBeUndefined();
    });

    it('saves only with all its services', () => {
        expect(bestSavingCents([combo], [CUT])).toBe(0);
        expect(bestSavingCents([combo], [CUT, BEARD, WASH])).toBe(1000);
        expect(bestSavingCents([combo, discount], [CUT, BEARD])).toBe(1000);
    });

    it('ticks all its services, or none when one cannot be booked', () => {
        expect(
            promotionSelections(combo, [service(CUT), service(BEARD, [7])]),
        ).toEqual([
            { serviceId: CUT, variantId: null },
            { serviceId: BEARD, variantId: 7 },
        ]);
        expect(promotionSelections(combo, [service(CUT)])).toEqual([]);
    });

    it('says which service it lacks once it was whole', () => {
        const names = (id: number) => (id === BEARD ? 'Barba' : 'Corte');

        expect(wholeCombos([combo, discount], [CUT, BEARD])).toEqual([12]);
        expect(comboBreaks([combo], [CUT], [12], names)).toEqual([
            { serviceId: BEARD, text: 'Sin Barba el combo no aplica' },
        ]);
        // Never whole, or nothing of it left: nothing to say.
        expect(comboBreaks([combo], [CUT], [], names)).toEqual([]);
        expect(comboBreaks([combo], [WASH], [12], names)).toEqual([]);
    });
});

describe('slotPromoMark', () => {
    it('says when the promotion of a time is only for a first visit', () => {
        expect(slotPromoMark({ name: 'x', saves: '5.00' })).toBe('con promo');
        expect(
            slotPromoMark({ name: 'x', saves: '5.00', firstVisit: true }),
        ).toBe('con promo · primera visita');
    });
});
