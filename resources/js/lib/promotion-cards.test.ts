import { describe, expect, it } from 'vite-plus/test';
import type { PromotionService } from '@/components/promotions/types';
import {
    cardOffers,
    giftCandidates,
    newPromotionFields,
    nextOccurrence,
    occasionDefaults,
    OCCASIONS,
    promotionPayload,
    upcomingOccasion,
} from './promotion-cards';
import { comboRange, suggestedComboCents } from './promotion-combo';
import { previewOf } from './promotion-preview';

const service = (
    id: number,
    name: string,
    overrides: Partial<PromotionService> = {},
): PromotionService => ({
    id,
    name,
    price: '35.00',
    minutes: 30,
    isAddon: false,
    canBeGift: true,
    ...overrides,
});

const services = [
    service(1, 'Corte'),
    service(2, 'Tinte', { price: '120.00', minutes: 90, canBeGift: false }),
    service(3, 'Barba', { price: '20.00', minutes: 20 }),
    service(4, 'Lavado', { price: '10.00', minutes: 10, isAddon: true }),
];

const context = {
    services,
    team: [
        { id: 7, name: 'Ana' },
        { id: 8, name: 'Luis' },
        { id: 9, name: 'Eva' },
    ],
    limits: { minPercent: 5, maxPercent: 70, maxDays: 90, maxActive: 3 },
    today: '2026-10-12',
    depositPercent: 30,
    salonName: 'Estudio Luz',
};

const occasion = (key: string) => OCCASIONS.find((one) => one.key === key)!;

describe('occasions', () => {
    it('finds the Sundays that move every year', () => {
        expect(nextOccurrence(occasion('mothers-day'), '2026-01-01')).toBe(
            '2026-05-10',
        );
        expect(nextOccurrence(occasion('fathers-day'), '2026-01-01')).toBe(
            '2026-06-21',
        );
        expect(nextOccurrence(occasion('mothers-day'), '2027-01-01')).toBe(
            '2027-05-09',
        );
    });

    it('goes to next year once the date has passed, not on the day itself', () => {
        expect(nextOccurrence(occasion('halloween'), '2026-10-31')).toBe(
            '2026-10-31',
        );
        expect(nextOccurrence(occasion('halloween'), '2026-11-01')).toBe(
            '2027-10-31',
        );
    });

    it('proposes from ten days before and up to the day, never in the past', () => {
        expect(occasionDefaults('halloween', '2026-10-12')).toEqual({
            overline: 'Halloween',
            starts_on: '2026-10-21',
            ends_on: '2026-10-31',
        });
        expect(occasionDefaults('halloween', '2026-10-28')?.starts_on).toBe(
            '2026-10-28',
        );
        expect(occasionDefaults('other', '2026-10-12')).toBeNull();
    });

    it('starts a campaign on the occasion that comes first', () => {
        expect(upcomingOccasion('2026-10-12').key).toBe('halloween');
        expect(upcomingOccasion('2026-12-26').key).toBe('valentine');
    });
});

describe('newPromotionFields', () => {
    it('proposes 20 % for two weeks with nothing picked', () => {
        expect(newPromotionFields('discount', '2026-10-12')).toMatchObject({
            mechanic: 'discount',
            services: [],
            percent: '20',
            starts_on: '2026-10-12',
            ends_on: '2026-10-26',
            weekdays: [],
        });
    });

    it('proposes Monday to Wednesday, 10 to 16, for thirty days in quiet hours', () => {
        expect(newPromotionFields('quiet_hours', '2026-10-12')).toMatchObject({
            weekdays: [1, 2, 3],
            from_time: '10:00',
            to_time: '16:00',
            percent: '20',
            ends_on: '2026-11-11',
        });
    });

    it('proposes a first-visit discount for thirty days, every day and hour', () => {
        expect(newPromotionFields('first_visit', '2026-10-12')).toMatchObject({
            mechanic: 'discount',
            percent: '20',
            ends_on: '2026-11-11',
            weekdays: [],
            once_per_client: false,
        });
    });

    it('fills a campaign with the next occasion', () => {
        expect(newPromotionFields('season', '2026-10-12')).toMatchObject({
            occasion: 'halloween',
            overline: 'Halloween',
            starts_on: '2026-10-21',
            ends_on: '2026-10-31',
        });
    });
});

describe('promotionPayload', () => {
    it('sends a percentage without the amount, and empty text as not set', () => {
        const fields = {
            ...newPromotionFields('discount', '2026-10-12'),
            services: [1],
            amount: '5',
        };

        expect(promotionPayload(fields, true)).toMatchObject({
            percent: '20',
            amount: null,
            gift_service_id: null,
            weekdays: null,
            from_time: null,
            quota: null,
            name: null,
            overline: null,
            publish: true,
        });
        expect(promotionPayload(fields, false)).not.toHaveProperty('publish');
    });

    it('sends who it is limited to, and none for all of the team', () => {
        const fields = newPromotionFields('discount', '2026-10-12');

        expect(promotionPayload(fields, false).staff).toEqual([]);
        expect(
            promotionPayload({ ...fields, staff: [8, 7] }, false).staff,
        ).toEqual([8, 7]);
    });

    it('sends only the gift for a gift', () => {
        const fields = {
            ...newPromotionFields('gift', '2026-10-12'),
            services: [1],
            gift_service_id: 4,
        };

        expect(promotionPayload(fields, false)).toMatchObject({
            mechanic: 'gift',
            percent: null,
            amount: null,
            gift_service_id: 4,
        });
    });

    it('sends only the price of a combo', () => {
        const fields = {
            ...newPromotionFields('combo', '2026-10-12'),
            services: [1, 3],
            combo_price: '49,50',
        };

        expect(promotionPayload(fields, false)).toMatchObject({
            card: 'combo',
            mechanic: 'combo',
            percent: null,
            amount: null,
            combo_price: '49.50',
            gift_service_id: null,
        });
        expect(
            promotionPayload(
                newPromotionFields('discount', '2026-10-12'),
                false,
            ).combo_price,
        ).toBeNull();
    });
});

describe('combo prices', () => {
    it('allows from 50 % to 5 % less, like the server', () => {
        expect(comboRange(5500)).toEqual({ lowest: 2750, highest: 5225 });
    });

    it('proposes about 10 % less in whole soles, within the range', () => {
        expect(suggestedComboCents(5500)).toBe(4900);
        expect(suggestedComboCents(15500)).toBe(13900);
        expect(suggestedComboCents(1000)).toBe(900);
    });
});

describe('cards and gifts', () => {
    it('offers add-ons first and then the shortest, only with one price', () => {
        expect(giftCandidates(services).map((one) => one.name)).toEqual([
            'Lavado',
            'Barba',
            'Corte',
        ]);
    });

    it('writes each example with the services of the salon', () => {
        expect(
            cardOffers(services, '2026-10-12').map((offer) => offer.example),
        ).toEqual([
            '20 % menos en Corte',
            'Lavado gratis con tu Corte',
            '20 % menos en Corte, de lunes a miércoles de 10:00 a 16:00',
            'Halloween · 20 % menos en Corte',
            '20 % menos en Corte en tu primera visita',
            'Corte + Tinte por S/ 139',
            'Segundo Corte a mitad de precio',
        ]);
    });

    it('has no example for a salon without services', () => {
        expect(cardOffers([], '2026-10-12')[0].example).toBeNull();
    });
});

describe('previewOf', () => {
    const discount = newPromotionFields('discount', '2026-10-12');

    it('says nothing until a service is picked', () => {
        expect(previewOf(discount, context)).toBeNull();
    });

    it('shows the price with the promotion and what it means for the salon', () => {
        const preview = previewOf({ ...discount, services: [1] }, context);

        expect(preview?.title).toBe('20 % menos en Corte');
        expect(preview?.lines).toEqual([
            {
                name: 'Corte',
                normalCents: 3500,
                promoCents: 2800,
                isGift: false,
            },
        ]);
        expect(preview?.summary).toEqual([
            'El cliente paga S/ 28 en vez de S/ 35.',
            'Quien atiende comisiona sobre S/ 28.',
            'Seña: S/ 8.40.',
        ]);
        expect(preview?.finePrint).toContain(
            'Promoción de Estudio Luz. Válido para citas del 12 al 26 de octubre de 2026. Aplica a: Corte.',
        );
        expect(preview?.finePrint).toContain(
            'El precio con promoción se reserva hasta el plazo para enviar la seña.',
        );
    });

    it('names who it is limited to in the fine print, in the team order', () => {
        const one = previewOf(
            { ...discount, services: [1], staff: [8] },
            context,
        );
        const two = previewOf(
            { ...discount, services: [1], staff: [9, 7] },
            context,
        );

        // Right after the places, like PromotionCopy::finePrint.
        expect(one?.finePrint).toContain(
            'mientras haya horarios libres. Solo con Luis: al reservar, elige a esa persona.',
        );
        expect(two?.finePrint).toContain(
            'Solo con Ana y Eva: al reservar, elige a una de estas personas.',
        );
        expect(
            previewOf({ ...discount, services: [1] }, context)?.finePrint,
        ).not.toContain('Solo con');
    });

    it('leaves the deposit out when the salon asks for none', () => {
        const preview = previewOf(
            { ...discount, services: [1] },
            { ...context, depositPercent: 0 },
        );

        expect(preview?.summary).toHaveLength(2);
        expect(preview?.finePrint).not.toContain('seña');
    });

    it('gives the gift at S/ 0 and warns about the commission', () => {
        const preview = previewOf(
            {
                ...newPromotionFields('gift', '2026-10-12'),
                services: [1],
                gift_service_id: 4,
            },
            context,
        );

        expect(preview?.title).toBe('Lavado gratis con tu Corte');
        expect(preview?.lines[1]).toMatchObject({
            name: 'Lavado',
            normalCents: 1000,
            promoCents: 0,
        });
        expect(preview?.summary).toContain(
            'Quien atiende no comisiona por el servicio de regalo.',
        );
        expect(preview?.finePrint).toContain(
            'El regalo (Lavado, precio normal S/ 10) se hace en la misma cita y alarga la cita 10 min.',
        );
    });

    it('says a first visit in the title and who may use it in the fine print', () => {
        const preview = previewOf(
            {
                ...newPromotionFields('first_visit', '2026-10-12'),
                services: [1],
            },
            context,
        );

        expect(preview?.title).toBe('20 % menos en Corte en tu primera visita');
        expect(preview?.finePrint).toContain(
            'Solo para tu primera reserva en Estudio Luz: no vale si ya tuviste una cita allí, aunque no hayas asistido, ni si tienes otra reservada. Las citas canceladas no cuentan. Lo comprobamos con tu cuenta y tu teléfono.',
        );
        expect(preview?.finePrint).not.toContain('Una por cliente.');
    });

    it('keeps the title the owner typed', () => {
        const preview = previewOf(
            { ...discount, services: [1], name: 'Semana del corte' },
            context,
        );

        expect(preview?.title).toBe('Semana del corte');
        // Under it, what it does in the system's words, as the client reads it.
        expect(preview?.subtitle).toBe('20 % menos en Corte');
    });
});

describe('previewOf a combo', () => {
    const combo = {
        ...newPromotionFields('combo', '2026-10-12'),
        services: [1, 3],
        combo_price: '45',
    };

    it('waits for two services and a price below what they cost apart', () => {
        expect(previewOf({ ...combo, services: [1] }, context)).toBeNull();
        expect(previewOf({ ...combo, combo_price: '55' }, context)).toBeNull();
    });

    it('shows the services together against their price apart', () => {
        const preview = previewOf(combo, context);

        expect(preview?.title).toBe('Combo Corte + Barba');
        expect(preview?.subtitle).toBe('Corte + Barba por S/ 45');
        expect(preview?.lines).toEqual([
            {
                name: 'Corte + Barba',
                normalCents: 5500,
                promoCents: 4500,
                isGift: false,
            },
        ]);
        expect(preview?.finePrint).toContain('sigues ahorrando S/ 10.');
        expect(preview?.summary).toEqual([
            'El cliente paga S/ 45 por Corte y Barba juntos, en vez de S/ 55.',
            'El ahorro se reparte entre los servicios según su precio. Quien atiende comisiona sobre lo cobrado.',
            'Seña: S/ 13.50.',
        ]);
    });

    it('says "desde" when a service has options, from its cheapest one', () => {
        const withOptions = {
            ...context,
            services: services.map((one) =>
                one.id === 3
                    ? { ...one, hasOptions: true, basePrice: '15.00' }
                    : one,
            ),
        };

        expect(previewOf(combo, withOptions)?.subtitle).toBe(
            'Corte + Barba desde S/ 45',
        );
        expect(previewOf(combo, withOptions)?.lines[0].normalCents).toBe(5000);
    });
});
