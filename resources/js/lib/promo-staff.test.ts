import { describe, expect, it } from 'vite-plus/test';
import type { BookableService } from '@/components/booking/types';
import type { PublicPromotion } from '@/components/public/types';
import { bestOffers, offerNote } from './promo-offers';
import {
    eitherOf,
    holdsWith,
    soleStaffId,
    staffPromotionHint,
    staffWithPromo,
} from './promo-staff';

const CUT = 1;
const BEARD = 2;
const ANA = { id: 7, name: 'Ana' };
const LEO = { id: 8, name: 'Leo' };

function promo(with_: Partial<PublicPromotion> = {}): PublicPromotion {
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
                name: 'Corte',
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

const withAna = promo({ staff: [ANA] });
const withBoth = promo({ staff: [ANA, LEO] });

function bookable(id: number, staffIds: number[]): BookableService {
    return { id, staffIds } as BookableService;
}

describe('a promotion limited to some of the team', () => {
    it('holds only when one of them is chosen, never with whoever is free', () => {
        expect(holdsWith(withAna, ANA.id)).toBe(true);
        expect(holdsWith(withAna, LEO.id)).toBe(false);
        expect(holdsWith(withAna, null)).toBe(false);
        expect(holdsWith(promo(), null)).toBe(true);
        expect(holdsWith(promo({ staff: [] }), LEO.id)).toBe(true);
    });

    it('says with whom under the crossed-out price', () => {
        expect(offerNote(bestOffers([withBoth]).get(CUT))).toBe(
            'con Ana o Leo',
        );
        expect(
            offerNote(
                bestOffers([promo({ staff: [ANA], when: 'de 9 a 18' })]).get(
                    CUT,
                ),
            ),
        ).toBe('con Ana, en horarios con promo');
        expect(offerNote(bestOffers([promo()]).get(CUT))).toBeNull();
        expect(eitherOf(['Ana', 'Leo', 'Eva'])).toBe('Ana, Leo o Eva');
    });

    it('marks who has it, only for the services chosen', () => {
        expect([...staffWithPromo([withBoth, promo()], [CUT])]).toEqual([
            ANA.id,
            LEO.id,
        ]);
        expect(staffWithPromo([withBoth], [BEARD]).size).toBe(0);
    });

    it('tells whom to choose before choosing', () => {
        expect(staffPromotionHint([withAna], [CUT])).toBe(
            '25 % menos en Corte vale solo con Ana: elige a esa persona para tenerla.',
        );
        expect(staffPromotionHint([withBoth], [CUT])).toBe(
            '25 % menos en Corte vale solo con Ana y Leo: elige a una de estas personas para tenerla.',
        );
        expect(staffPromotionHint([promo()], [CUT])).toBeNull();
    });

    it('starts the wizard with its only professional, when they do it all', () => {
        expect(soleStaffId(withAna, [bookable(CUT, [ANA.id])])).toBe(ANA.id);
        expect(soleStaffId(withAna, [bookable(CUT, [LEO.id])])).toBeNull();
        expect(soleStaffId(withBoth, [bookable(CUT, [ANA.id])])).toBeNull();
        expect(soleStaffId(withAna, [])).toBeNull();
        expect(soleStaffId(undefined, [bookable(CUT, [ANA.id])])).toBeNull();
    });
});
