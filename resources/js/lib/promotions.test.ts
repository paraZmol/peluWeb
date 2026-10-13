import { describe, expect, it } from 'vite-plus/test';
import {
    addDays,
    amountPrice,
    dateRange,
    depositCents,
    instagramText,
    percentPrice,
    promotionFinePrint,
    promotionTitle,
    promotionWhen,
    whatsappShareUrl,
} from './promotions';
import type { CopyTerms } from './promotions';

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
});

describe('promotionFinePrint', () => {
    it('writes the fine print in full, the same way every time', () => {
        expect(
            promotionFinePrint(
                terms('discount', { percent: 20 }),
                ['Corte', 'Barba'],
                null,
                false,
            ),
        ).toBe(
            'Válido para citas del 5 de octubre al 18 de octubre. Aplica a: Corte, Barba. ' +
                'Sin límite de cupos, sujeto a horarios disponibles. ' +
                'Con reserva previa. No acumulable con otras promociones. Precios en soles con IGV incluido.',
        );

        const quiet = terms('discount', {
            percent: 20,
            weekdays: [1, 2, 3],
            fromTime: '10:00',
            toTime: '16:00',
        });

        expect(promotionFinePrint(quiet, ['Corte'], 30, true)).toBe(
            'Válido para citas del 5 de octubre al 18 de octubre. Aplica a: Corte. ' +
                'Solo lunes, martes y miércoles, de 10:00 a 16:00. Cupos: 30. Una por cliente. ' +
                'Con reserva previa. No acumulable con otras promociones. Precios en soles con IGV incluido.',
        );
    });

    it('adds what a gift is not', () => {
        expect(
            promotionFinePrint(terms('gift'), ['Corte'], null, false),
        ).toContain(
            'El servicio de regalo se realiza en la misma cita y no es canjeable por dinero ni por otro servicio.',
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
        startsOn: '2026-10-21',
        endsOn: '2026-10-31',
        shareUrl: 'https://peluweb.test/salon/p/abc',
    };

    it('sends the title and the link through WhatsApp', () => {
        expect(whatsappShareUrl(promotion)).toBe(
            `https://wa.me/?text=${encodeURIComponent('Halloween · 20 % menos en Corte\nhttps://peluweb.test/salon/p/abc')}`,
        );
    });

    it('writes a caption with title, dates and link', () => {
        expect(instagramText({ ...promotion, overline: null })).toBe(
            '20 % menos en Corte\nDel 21 de octubre al 31 de octubre.\nReserva aquí: https://peluweb.test/salon/p/abc',
        );
    });
});
