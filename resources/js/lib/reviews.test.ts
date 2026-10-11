import { describe, expect, it } from 'vite-plus/test';
import {
    authorInitial,
    canReport,
    emptyReviewsMessage,
    formatAverage,
    isRatingChosen,
    isReportReasonValid,
    ratingText,
    reviewAudienceNotice,
    reviewStatus,
    starFill,
    starsLabel,
    STAR_POSITIONS,
    verifiedCountLabel,
} from './reviews';

const flags = { isReported: false, isHidden: false, wasKept: false };

describe('starsLabel', () => {
    it('agrees in number', () => {
        expect(starsLabel(1)).toBe('1 estrella');
        expect(starsLabel(4)).toBe('4 estrellas');
    });
});

describe('ratingText', () => {
    it('reads a whole rating without decimals', () => {
        expect(ratingText(4)).toBe('4 de 5 estrellas');
    });

    it('keeps one decimal of an average', () => {
        expect(ratingText(4.5)).toBe('4.5 de 5 estrellas');
    });
});

describe('formatAverage', () => {
    it('always shows one decimal', () => {
        expect(formatAverage(4)).toBe('4.0');
        expect(formatAverage(4.3)).toBe('4.3');
    });
});

describe('verifiedCountLabel', () => {
    it('agrees in number', () => {
        expect(verifiedCountLabel(1)).toBe('1 reseña verificada');
        expect(verifiedCountLabel(12)).toBe('12 reseñas verificadas');
        expect(verifiedCountLabel(0)).toBe('0 reseñas verificadas');
    });
});

describe('starFill', () => {
    it('fills whole stars up to the rating', () => {
        expect(STAR_POSITIONS.map((star) => starFill(4, star))).toEqual([
            1, 1, 1, 1, 0,
        ]);
    });

    it('fills part of the star an average falls in', () => {
        expect(starFill(3.5, 3)).toBe(1);
        expect(starFill(3.5, 4)).toBe(0.5);
        expect(starFill(3.5, 5)).toBe(0);
    });
});

describe('isRatingChosen', () => {
    it('needs 1 to 5 whole stars', () => {
        expect(isRatingChosen(0)).toBe(false);
        expect(isRatingChosen(1)).toBe(true);
        expect(isRatingChosen(5)).toBe(true);
        expect(isRatingChosen(6)).toBe(false);
        expect(isRatingChosen(2.5)).toBe(false);
    });
});

describe('reviewAudienceNotice', () => {
    it('warns that a public review carries the first name', () => {
        expect(reviewAudienceNotice(true, 'Salón Uno')).toBe(
            'Tu reseña se publicará con tu nombre de pila en la página de Salón Uno. No incluyas datos personales de otras personas.',
        );
    });

    it('says only the team reads a private one', () => {
        expect(reviewAudienceNotice(false, 'Salón Uno')).toBe(
            'Solo la leerá el equipo de Salón Uno.',
        );
    });
});

describe('emptyReviewsMessage', () => {
    it('says the salon is not asking for them when hidden', () => {
        expect(emptyReviewsMessage('hidden')).toBe(
            'No estás recibiendo reseñas.',
        );
    });

    it('says how they arrive in the other modes', () => {
        const message =
            'Todavía no hay reseñas. Se piden por correo después de cada cita atendida.';

        expect(emptyReviewsMessage('private')).toBe(message);
        expect(emptyReviewsMessage('public')).toBe(message);
    });
});

describe('reviewStatus', () => {
    it('is null when nobody reported it', () => {
        expect(reviewStatus(flags)).toBeNull();
    });

    it('says it waits for the platform', () => {
        expect(reviewStatus({ ...flags, isReported: true })?.label).toBe(
            'En revisión',
        );
    });

    it('says the platform took it down, above anything else', () => {
        expect(
            reviewStatus({ ...flags, isHidden: true, isReported: true })?.label,
        ).toBe('Oculta por peluWeb');
    });

    it('says it was looked at and left up', () => {
        expect(reviewStatus({ ...flags, wasKept: true })?.label).toBe(
            'Revisada: se mantiene',
        );
    });
});

describe('canReport', () => {
    it('allows a review nobody reported', () => {
        expect(canReport(flags)).toBe(true);
    });

    it('reports once: not while waiting, nor after the verdict', () => {
        expect(canReport({ ...flags, isReported: true })).toBe(false);
        expect(canReport({ ...flags, isHidden: true })).toBe(false);
        expect(canReport({ ...flags, wasKept: true })).toBe(false);
    });
});

describe('isReportReasonValid', () => {
    it('needs at least 10 characters, spaces around not counted', () => {
        expect(isReportReasonValid('  insulta  ', 500)).toBe(false);
        expect(isReportReasonValid('Insulta al equipo', 500)).toBe(true);
    });

    it('stops at the limit', () => {
        expect(isReportReasonValid('a'.repeat(501), 500)).toBe(false);
        expect(isReportReasonValid('a'.repeat(500), 500)).toBe(true);
    });
});

describe('authorInitial', () => {
    it('takes the first letter in capitals', () => {
        expect(authorInitial('maría')).toBe('M');
    });

    it('never comes back empty', () => {
        expect(authorInitial('  ')).toBe('·');
    });
});
