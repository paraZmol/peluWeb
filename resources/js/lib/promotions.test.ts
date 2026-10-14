import { describe, expect, it } from 'vite-plus/test';
import {
    addDays,
    amountPrice,
    dateRange,
    depositCents,
    instagramText,
    percentPrice,
    promotionFinePrint,
    promotionPeriod,
    promotionTitle,
    promotionWhen,
    quotaLabel,
    whatsappShareUrl,
} from './promotions';
import type { CopyTerms, PromotionFacts } from './promotions';

// The same cases as tests/Unit/Promotions/PromotionCopyTest.php: both
// sides must write the same words.
function terms(
    mechanic: CopyTerms['mechanic'],
    overrides: Partial<CopyTerms> = {},
): CopyTerms {
    return {
        mechanic,
        percent: null,
        amountCents: null,
        startsOn: '2026-10-05',
        endsOn: '2026-10-18',
        weekdays: null,
        fromTime: null,
        toTime: null,
        newClientsOnly: false,
        ...overrides,
    };
}

describe('promotionTitle', () => {
    it('writes the title from what the promotion does', () => {
        expect(
            promotionTitle(terms('discount', { percent: 20 }), ['Corte']),
        ).toBe('20 % menos en Corte');
        expect(
            promotionTitle(terms('discount', { amountCents: 1000 }), [
                'Manicure',
                'Pedicure',
            ]),
        ).toBe('S/ 10 menos en Manicure y Pedicure');
        expect(
            promotionTitle(terms('discount', { amountCents: 1050 }), [
                'Manicure',
            ]),
        ).toBe('S/ 10.50 menos en Manicure');
        expect(
            promotionTitle(terms('discount', { percent: 15 }), [
                'Corte',
                'Barba',
                'Cejas',
                'Tinte',
            ]),
        ).toBe('15 % menos en Corte, Barba y más');
        expect(promotionTitle(terms('gift'), ['Corte'], 'Rasurado')).toBe(
            'Rasurado gratis con tu Corte',
        );
    });

    it('says when it is only for the first visit', () => {
        expect(
            promotionTitle(
                terms('discount', { percent: 20, newClientsOnly: true }),
                ['Corte'],
            ),
        ).toBe('20 % menos en Corte en tu primera visita');
        expect(
            promotionTitle(
                terms('discount', { amountCents: 1000, newClientsOnly: true }),
                ['Manicure'],
            ),
        ).toBe('S/ 10 menos en Manicure en tu primera visita');
    });
});

describe('promotionFinePrint', () => {
    const facts: PromotionFacts = {
        salonName: 'Estudio Luz',
        quota: null,
        isOncePerClient: false,
        hasDeposit: false,
    };
    const closing = (deposit: string) =>
        'Si cambias tu cita, la promoción se mantiene solo si la nueva fecha y hora cumplen estas condiciones. ' +
        deposit +
        'Con reserva previa. No se suma a otras promociones: si tu cita cumple más de una, se aplica la de mayor ahorro. ' +
        'Precios finales en soles, con impuestos incluidos.';

    it('writes the fine print in full, the same way every time', () => {
        expect(
            promotionFinePrint(
                terms('discount', { percent: 20 }),
                ['Corte', 'Barba'],
                facts,
            ),
        ).toBe(
            'Promoción de Estudio Luz. Válido para citas del 5 al 18 de octubre de 2026. Aplica a: Corte, Barba. ' +
                'Sin límite de cupos: vale para todas las citas que se reserven dentro de estas fechas, mientras haya horarios libres. ' +
                closing(''),
        );

        const quiet = terms('discount', {
            percent: 20,
            weekdays: [1, 2, 3],
            fromTime: '10:00',
            toTime: '16:00',
        });

        expect(
            promotionFinePrint(quiet, ['Corte'], {
                ...facts,
                quota: 30,
                isOncePerClient: true,
                hasDeposit: true,
            }),
        ).toBe(
            'Promoción de Estudio Luz. Válido para citas del 5 al 18 de octubre de 2026. Aplica a: Corte. ' +
                'Solo lunes, martes y miércoles, en citas que empiecen entre las 10:00 y las 16:00. Cupos: 30 reservas. Una por cliente. ' +
                closing(
                    'El precio con promoción se reserva hasta el plazo para enviar la seña. ',
                ),
        );
    });

    it('says only someone new may use it, instead of once each', () => {
        expect(
            promotionFinePrint(
                terms('discount', { percent: 20, newClientsOnly: true }),
                ['Corte'],
                { ...facts, quota: 10, isOncePerClient: true },
            ),
        ).toBe(
            'Promoción de Estudio Luz. Válido para citas del 5 al 18 de octubre de 2026. Aplica a: Corte. ' +
                'Cupos: 10 reservas. Solo para la primera visita: vale si nunca tuviste una cita en Estudio Luz, ni tienes otra reservada. ' +
                closing(''),
        );
    });

    it('says a fixed amount comes off once, when it covers more than one service', () => {
        const amount = terms('discount', { amountCents: 1000 });
        const once =
            'El descuento de S/ 10 se aplica una sola vez por reserva.';

        expect(
            promotionFinePrint(amount, ['Manicure', 'Pedicure'], facts),
        ).toContain(once);
        expect(promotionFinePrint(amount, ['Manicure'], facts)).not.toContain(
            once,
        );
    });

    it('says what a gift is worth, what it adds and what it is not', () => {
        expect(
            promotionFinePrint(terms('gift'), ['Corte'], {
                ...facts,
                gift: { name: 'Rasurado', priceCents: 1200, minutes: 15 },
            }),
        ).toContain(
            'El regalo (Rasurado, precio normal S/ 12) se hace en la misma cita y alarga la cita 15 min. No se cambia por dinero ni por otro servicio.',
        );
    });
});

describe('promotionPeriod', () => {
    it('writes the dates with the year, and each month when they differ', () => {
        expect(promotionPeriod('2026-10-05', '2026-10-18')).toBe(
            'del 5 al 18 de octubre de 2026',
        );
        expect(promotionPeriod('2026-10-20', '2026-11-08')).toBe(
            'del 20 de octubre al 8 de noviembre de 2026',
        );
        expect(promotionPeriod('2026-12-15', '2027-01-10')).toBe(
            'del 15 de diciembre de 2026 al 10 de enero de 2027',
        );
    });
});

describe('promotionWhen', () => {
    it('says only the days, or only the hours, when it names one of them', () => {
        expect(promotionWhen(terms('discount', { weekdays: [6] }))).toBe(
            'sábado',
        );
        expect(
            promotionWhen(
                terms('discount', { fromTime: '09:00', toTime: '12:00' }),
            ),
        ).toBe('de 09:00 a 12:00');
        expect(promotionWhen(terms('discount'))).toBeNull();
    });

    it('names the days in the order of the week, however they were picked', () => {
        expect(promotionWhen(terms('discount', { weekdays: [3, 1] }))).toBe(
            'lunes y miércoles',
        );
    });
});

describe('prices with a promotion', () => {
    it('rounds a percentage down to 10 céntimos, never to nothing', () => {
        expect(percentPrice(3500, 20)).toBe(2800);
        expect(percentPrice(3550, 15)).toBe(3010);
        expect(percentPrice(15, 70)).toBe(4);
    });

    it('takes a fixed amount off only what costs more than it', () => {
        expect(amountPrice(3500, 1000)).toBe(2500);
        expect(amountPrice(3555, 1000)).toBe(2550);
        expect(amountPrice(1000, 1000)).toBe(1000);
    });

    it('asks the deposit on the total with the promotion', () => {
        expect(depositCents(2800, 30)).toBe(840);
        expect(depositCents(2805, 30)).toBe(842);
    });
});

describe('dates', () => {
    it('adds calendar days across months and years', () => {
        expect(addDays('2026-10-07', 14)).toBe('2026-10-21');
        expect(addDays('2026-12-25', 10)).toBe('2027-01-04');
        expect(addDays('2026-03-05', -10)).toBe('2026-02-23');
    });

    it('writes a short range', () => {
        expect(dateRange('2026-10-07', '2026-10-21')).toBe('7 oct – 21 oct');
    });
});

describe('sharing', () => {
    const promotion = {
        name: '20 % menos en Corte',
        overline: 'Halloween',
        summary: null,
        period: 'del 21 al 31 de octubre de 2026',
        shareUrl: 'https://peluweb.test/salon/p/abc',
        form: { quota: null },
    };

    it('sends the title, the dates and the link through WhatsApp', () => {
        expect(whatsappShareUrl(promotion)).toBe(
            `https://wa.me/?text=${encodeURIComponent('Halloween · 20 % menos en Corte\nVálido del 21 al 31 de octubre de 2026.\nCondiciones y reserva: https://peluweb.test/salon/p/abc')}`,
        );
    });

    it('writes a caption with title, dates, places and link', () => {
        expect(
            instagramText({
                ...promotion,
                overline: null,
                form: { quota: 30 },
            }),
        ).toBe(
            '20 % menos en Corte\nVálido del 21 al 31 de octubre de 2026.\nCupos: 30 reservas.\nCondiciones y reserva: https://peluweb.test/salon/p/abc',
        );
    });

    it('says what it does under a title of the salon’s own', () => {
        expect(
            instagramText({
                ...promotion,
                name: 'Noche de brujas',
                overline: null,
                summary: '20 % menos en Corte',
            }),
        ).toBe(
            'Noche de brujas\n20 % menos en Corte.\nVálido del 21 al 31 de octubre de 2026.\nCondiciones y reserva: https://peluweb.test/salon/p/abc',
        );
    });

    it('counts a single place in the singular', () => {
        expect(quotaLabel(1)).toBe('Cupos: 1 reserva');
        expect(quotaLabel(null)).toBeNull();
    });
});
