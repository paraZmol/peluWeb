import { describe, expect, it } from 'vite-plus/test';
import type { PromotionService } from '@/components/promotions/types';
import type { PublicPromotion } from '@/components/public/types';
import { bookedLines, keptCompanion, withCompanion } from './companion';
import { bestOffers, pairPercent, pairSavingCents } from './promo-offers';
import {
    newPromotionFields,
    pairCandidates,
    promotionPayload,
} from './promotion-cards';
import { previewOf } from './promotion-preview';
import { promotionFinePrint, promotionTitle } from './promotions';

// "Ven con alguien" (docs/promociones.md): the same words as
// App\Domain\Promotions\PromotionCopy, and the second line it books.

const CUT = 1;

const terms = (percent: number) => ({
    mechanic: 'pair' as const,
    percent,
    amountCents: null,
    startsOn: '2026-10-05',
    endsOn: '2026-10-18',
    weekdays: null,
    fromTime: null,
    toTime: null,
    newClientsOnly: false,
});

const facts = {
    salonName: 'Estudio Luz',
    quota: null,
    isOncePerClient: false,
    hasDeposit: false,
};

function pair(with_: Partial<PublicPromotion> = {}): PublicPromotion {
    return {
        id: 20,
        name: 'Segundo Corte a mitad de precio',
        overline: null,
        mechanic: 'pair',
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
                name: 'Corte',
                normal: '35.00',
                promo: '35.00',
                isFrom: true,
            },
        ],
        gift: null,
        combo: null,
        ...with_,
    };
}

describe('the words of «Ven con alguien»', () => {
    it('titles the half price and the 2x1', () => {
        expect(promotionTitle(terms(50), ['Corte'])).toBe(
            'Segundo Corte a mitad de precio',
        );
        expect(promotionTitle(terms(100), ['Corte'])).toBe('2x1 en Corte');
    });

    it('says in the fine print who pays whole and what happens if one comes alone', () => {
        const half = promotionFinePrint(terms(50), ['Corte'], facts);
        const free = promotionFinePrint(terms(100), ['Corte'], facts);

        expect(half).toContain(
            'Vale para dos personas que reservan juntas, en una sola reserva, el mismo servicio, atendidas una después de otra por la misma persona del equipo. Si eligen opciones de distinto precio, se cobra entero el de mayor precio y el otro va a mitad de precio. Al reservar se pide solo el nombre de quien acompaña. Si llega una sola persona, la promoción no aplica: se cobra a precio normal solo el servicio de quien vino y la seña pagada se descuenta de ese monto. El beneficio no se cambia por dinero ni se pasa a otra cita. Si cambias tu cita',
        );
        expect(free).toContain('y el otro es gratis.');
    });
});

describe('the owner’s form', () => {
    const services: PromotionService[] = [
        {
            id: 1,
            name: 'Corte',
            price: '35.00',
            minutes: 30,
            isAddon: false,
            canBeGift: false,
        },
        {
            id: 2,
            name: 'Tinte',
            price: '120.00',
            minutes: 90,
            isAddon: false,
            canBeGift: false,
            requiresEvaluation: true,
        },
        {
            id: 3,
            name: 'Lavado',
            price: '10.00',
            minutes: 10,
            isAddon: true,
            canBeGift: true,
        },
    ];
    const context = {
        services,
        limits: { minPercent: 5, maxPercent: 70, maxDays: 90, maxActive: 3 },
        today: '2026-10-12',
        depositPercent: 20,
        salonName: 'Estudio Luz',
    };

    it('offers only services with one fixed price and no add-ons', () => {
        expect(pairCandidates(services).map((one) => one.id)).toEqual([1]);
    });

    it('starts at half price and sends only the percentage', () => {
        const fields = newPromotionFields('pair', '2026-10-12');

        expect(fields.mechanic).toBe('pair');
        expect(
            promotionPayload({ ...fields, services: [1] }, false),
        ).toMatchObject({
            mechanic: 'pair',
            percent: '50',
            amount: null,
            combo_price: null,
            gift_service_id: null,
        });
    });

    it('previews two of the same and what the one who attends earns on', () => {
        const fields = {
            ...newPromotionFields('pair', '2026-10-12'),
            services: [1],
        };
        const preview = previewOf(fields, context);

        expect(preview?.title).toBe('Segundo Corte a mitad de precio');
        expect(preview?.lines.map((line) => line.promoCents)).toEqual([
            3500, 1750,
        ]);
        expect(preview?.summary).toEqual([
            'Dos personas pagan S/ 52.50 en vez de S/ 70.',
            'Quien atiende hace dos servicios y comisiona sobre S/ 52.50.',
            'Seña: S/ 10.50.',
        ]);

        const free = previewOf({ ...fields, percent: '100' }, context);

        expect(free?.title).toBe('2x1 en Corte');
        expect(free?.lines[1]).toMatchObject({ promoCents: 0, isGift: true });
    });
});

describe('the wizard', () => {
    const lucia = { serviceId: CUT, name: ' Lucía ', variantId: 8 };
    const one = [{ serviceId: CUT, variantId: 7 }];

    it('reads the benefit from the server, else from the system’s title', () => {
        expect(pairPercent(pair())).toBe(50);
        expect(pairPercent(pair({ name: '2x1 en Corte' }))).toBe(100);
        expect(
            pairPercent(
                pair({ name: 'Ven con tu pata', summary: '2x1 en Corte' }),
            ),
        ).toBe(100);
        expect(pairPercent(pair({ pair: { percent: 100 } }))).toBe(100);
    });

    it('saves the cheaper one at its percentage, rounded down to 10 céntimos', () => {
        expect(pairSavingCents(pair(), ['45.00', '35.00'])).toBe(1750);
        expect(pairSavingCents(pair(), ['35.50', '35.50'])).toBe(1780);
        expect(
            pairSavingCents(pair({ pair: { percent: 100 } }), [
                '45.00',
                '35.00',
            ]),
        ).toBe(3500);
    });

    it('marks its services without crossing out a price', () => {
        expect(bestOffers([pair()]).get(CUT)).toMatchObject({
            isCombo: true,
            label: 'Para dos',
        });
    });

    it('adds the second line only once who comes along has a name', () => {
        expect(bookedLines(one, { ...lucia, name: '  ' })).toEqual(one);
        expect(bookedLines(one, lucia)).toEqual([
            ...one,
            { serviceId: CUT, variantId: 8, companion: 'Lucía' },
        ]);
        expect(withCompanion([{ name: 'Corte' }], lucia)).toEqual([
            { name: 'Corte' },
            { name: 'Corte · para Lucía' },
        ]);
    });

    it('drops who comes along when the selection is no longer one service of a pair', () => {
        expect(
            keptCompanion([...one, { serviceId: 2, variantId: null }], lucia),
        ).toBeNull();
        expect(
            keptCompanion([{ serviceId: CUT, variantId: 9 }], lucia),
        ).toEqual(lucia);
    });
});
