import { describe, expect, it } from 'vite-plus/test';
import type {
    Promotion,
    PromotionService,
} from '@/components/promotions/types';
import { posterContent, posterFileName } from './promo-poster';
import { savedPromotionFields } from './promotion-cards';
import { previewOf } from './promotion-preview';

function service(
    id: number,
    name: string,
    with_: Partial<PromotionService> = {},
): PromotionService {
    return {
        id,
        name,
        price: '35.00',
        minutes: 30,
        isAddon: false,
        canBeGift: true,
        ...with_,
    };
}

const services = [
    service(1, 'Corte'),
    service(2, 'Barba', { price: '20.00' }),
    service(3, 'Lavado', { price: '10.00', minutes: 10, isAddon: true }),
];

const context = {
    services,
    team: [
        { id: 7, name: 'Ana' },
        { id: 8, name: 'Leo' },
    ],
    limits: { minPercent: 5, maxPercent: 70, maxDays: 90, maxActive: 3 },
    today: '2026-10-12',
    depositPercent: 0,
    salonName: 'Estudio Luz',
};

function promotion(with_: Partial<Promotion> = {}): Promotion {
    const base: Promotion = {
        id: 12,
        card: 'discount',
        mechanic: 'discount',
        name: '20 % menos en Corte',
        overline: null,
        state: 'active',
        stateLabel: 'Activa',
        startsOn: '2026-10-12',
        endsOn: '2026-10-26',
        period: 'del 12 al 26 de octubre de 2026',
        summary: null,
        finePrint: '',
        when: null,
        shareUrl: 'https://peluweb.test/s/estudio-luz/promo/abc',
        isLocked: false,
        form: {
            services: [1],
            percent: 20,
            amount: null,
            gift_service_id: null,
            weekdays: null,
            from_time: null,
            to_time: null,
            quota: null,
            once_per_client: false,
            staff: [],
        },
        results: {
            bookings: 0,
            completed: 0,
            charged: '0.00',
            givenAway: '0.00',
            placesUsed: 0,
        },
    };

    return { ...base, ...with_, form: { ...base.form, ...with_.form } };
}

function contentOf(saved: Promotion, staffNames: string[] = []) {
    return posterContent({
        promotion: saved,
        preview: previewOf(savedPromotionFields(saved, saved.name), context),
        services,
        staffNames,
        salonName: context.salonName,
    });
}

describe('the image to share', () => {
    it('says the offer, how long it runs with its year and where the conditions are', () => {
        expect(contentOf(promotion())).toEqual({
            salonName: 'Estudio Luz',
            overline: null,
            title: '20 % menos en Corte',
            subtitle: null,
            benefit: {
                normal: 'S/ 35',
                headline: 'S/ 28',
                caption: 'Corte',
            },
            facts: ['Válido para citas del 12 al 26 de octubre de 2026'],
            link: 'Condiciones y reserva: peluweb.test/s/estudio-luz/promo/abc',
        });
    });

    it('adds with whom, the days and hours, and the places when it has them', () => {
        const content = contentOf(
            promotion({
                name: 'Martes de barbería',
                summary: '20 % menos en Corte',
                overline: 'Halloween',
                when: 'lunes y martes, de 10:00 a 16:00',
                form: { quota: 30, staff: [8] } as Promotion['form'],
            }),
            ['Leo'],
        );

        expect(content.title).toBe('Martes de barbería');
        expect(content.subtitle).toBe('20 % menos en Corte');
        expect(content.overline).toBe('Halloween');
        expect(content.facts).toEqual([
            'Solo con Leo',
            'Solo lunes y martes, de 10:00 a 16:00',
            'Válido para citas del 12 al 26 de octubre de 2026',
            'Cupos: 30 reservas',
        ]);
    });

    it('says a combo as everything for one price and what it saves', () => {
        const content = contentOf(
            promotion({
                card: 'combo',
                mechanic: 'combo',
                name: 'Combo Corte + Barba',
                form: {
                    services: [1, 2],
                    percent: null,
                    combo_price: '45.00',
                } as Promotion['form'],
            }),
        );

        expect(content.benefit).toEqual({
            normal: 'S/ 55',
            headline: 'Todo por S/ 45',
            caption: 'Corte + Barba, juntos: ahorras S/ 10',
        });
    });

    it('says a 2x1, an amount off and a gift in big', () => {
        const pair = contentOf(
            promotion({
                card: 'pair',
                mechanic: 'pair',
                form: { percent: 100 } as Promotion['form'],
            }),
        );
        const amount = contentOf(
            promotion({
                form: { percent: null, amount: '10.00' } as Promotion['form'],
            }),
        );
        const gift = contentOf(
            promotion({
                card: 'gift',
                mechanic: 'gift',
                form: {
                    percent: null,
                    gift_service_id: 3,
                } as Promotion['form'],
            }),
        );

        expect(pair.benefit).toEqual({
            normal: 'S/ 70',
            headline: '2x1',
            caption:
                'Los dos por S/ 35: Corte para ti y para quien te acompaña',
        });
        expect(amount.benefit).toMatchObject({
            normal: 'S/ 35',
            headline: 'S/ 25',
        });
        expect(gift.benefit).toEqual({
            normal: 'S/ 10',
            headline: 'Gratis',
            caption: 'Lavado con tu Corte',
        });
    });

    it('leaves the offer out when its services are gone, never the dates', () => {
        const content = contentOf(
            promotion({ form: { services: [99] } as Promotion['form'] }),
        );

        expect(content.benefit).toBeNull();
        expect(content.facts).toContain(
            'Válido para citas del 12 al 26 de octubre de 2026',
        );
        expect(posterFileName({ id: 12 })).toBe('promocion-12.png');
    });
});
