import type { OwnerReview, ReviewMode } from '@/components/reviews/types';

export const MAX_RATING = 5;

/** A review's comment, and the salon's answer to it. */
export const COMMENT_MAX_LENGTH = 600;

/** The shortest reason the platform accepts for a report. */
export const REPORT_MIN_LENGTH = 10;

/** 1, 2, 3, 4, 5: the positions of the stars. */
export const STAR_POSITIONS = Array.from(
    { length: MAX_RATING },
    (_, index) => index + 1,
);

/** 1 → "1 estrella", 4 → "4 estrellas". */
export function starsLabel(rating: number): string {
    return rating === 1 ? '1 estrella' : `${rating} estrellas`;
}

/** 4 → "4 de 5 estrellas", 4.5 → "4.5 de 5 estrellas": what a reader hears. */
export function ratingText(rating: number): string {
    const value = Number.isInteger(rating) ? String(rating) : rating.toFixed(1);

    return `${value} de ${MAX_RATING} estrellas`;
}

/** 4 → "4.0", 4.25 → "4.3": the average always with one decimal. */
export function formatAverage(average: number): string {
    return average.toFixed(1);
}

/** 1 → "1 reseña verificada", 12 → "12 reseñas verificadas". */
export function verifiedCountLabel(count: number): string {
    return count === 1 ? '1 reseña verificada' : `${count} reseñas verificadas`;
}

/**
 * How much of the star at `position` an average fills, from 0 to 1:
 * 3.5 fills three stars and half of the fourth.
 */
export function starFill(rating: number, position: number): number {
    return Math.min(1, Math.max(0, rating - (position - 1)));
}

/** A review is sent with 1 to 5 whole stars, never without. */
export function isRatingChosen(rating: number): boolean {
    return Number.isInteger(rating) && rating >= 1 && rating <= MAX_RATING;
}

/** Who will read the review, said before it is sent. */
export function reviewAudienceNotice(
    isPublic: boolean,
    salonName: string,
): string {
    return isPublic
        ? `Tu reseña se publicará con tu nombre de pila en la página de ${salonName}. No incluyas datos personales de otras personas.`
        : `Solo la leerá el equipo de ${salonName}.`;
}

/** Why the salon's list is empty, which depends on whether it asks for them. */
export function emptyReviewsMessage(mode: ReviewMode): string {
    return mode === 'hidden'
        ? 'No estás recibiendo reseñas.'
        : 'Todavía no hay reseñas. Se piden por correo después de cada cita atendida.';
}

export type ReviewStatus = {
    key: 'hidden' | 'reported' | 'kept';
    label: string;
};

/** Where a reported review stands; null when nobody reported it. */
export function reviewStatus(
    review: Pick<OwnerReview, 'isReported' | 'isHidden' | 'wasKept'>,
): ReviewStatus | null {
    if (review.isHidden) {
        return { key: 'hidden', label: 'Oculta por peluWeb' };
    }

    if (review.isReported) {
        return { key: 'reported', label: 'En revisión' };
    }

    return review.wasKept
        ? { key: 'kept', label: 'Revisada: se mantiene' }
        : null;
}

/** A review is reported once: not while it waits, nor after the verdict. */
export function canReport(
    review: Pick<OwnerReview, 'isReported' | 'isHidden' | 'wasKept'>,
): boolean {
    return !review.isReported && !review.isHidden && !review.wasKept;
}

/** Said beside a review written while the salon kept them private. */
export const PRIVATE_REVIEW_NOTE =
    'Se escribió cuando tus reseñas eran privadas: no aparece en tu página.';

/** A report needs a reason long enough to be looked at. */
export function isReportReasonValid(
    reason: string,
    maxLength: number,
): boolean {
    const length = reason.trim().length;

    return length >= REPORT_MIN_LENGTH && length <= maxLength;
}

/** Initial of the author for the round mark beside a review. */
export function authorInitial(author: string): string {
    return author.trim().charAt(0).toUpperCase() || '·';
}
