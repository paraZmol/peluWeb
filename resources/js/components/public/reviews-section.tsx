import { Star } from 'lucide-react';
import { useState } from 'react';
import { RatingSummary } from '@/components/reviews/rating-summary';
import { ReviewCard } from '@/components/reviews/review-card';
import type { PublicReviews } from '@/components/reviews/types';
import { useSalonTheme } from '@/components/salon/brand-theme';
import type { SalonThemeLayout } from '@/components/salon/salon-themes';
import { PUBLIC_REVIEWS_NOTE } from '@/lib/reviews';
import { CHIP_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { SectionHeading } from './section-heading';

/** The panel of the average, as each template makes things stand out. */
const SUMMARY: Record<SalonThemeLayout['highlight'], string> = {
    outline: 'border border-brand-line bg-brand-soft',
    soft: 'bg-brand-soft',
    solid: 'skin-primary',
};

/** Shown at first; the rest wait behind "Ver más reseñas". */
const FIRST_REVIEWS = 6;

type Props = { reviews: PublicReviews; salonName: string };

/**
 * "Reseñas": what people said after a visit that happened. Rendered only
 * while the salon keeps its reviews public (the server sends null otherwise).
 */
export function ReviewsSection({ reviews, salonName }: Props) {
    const { layout } = useSalonTheme();
    const [showsAll, setShowsAll] = useState(false);
    const shown = showsAll
        ? reviews.items
        : reviews.items.slice(0, FIRST_REVIEWS);
    const hidden = reviews.items.length - shown.length;

    return (
        <section
            id="resenas"
            aria-labelledby="resenas-titulo"
            className="flex scroll-mt-6 flex-col gap-6"
        >
            <SectionHeading
                id="resenas-titulo"
                eyebrow="Opiniones"
                title="Reseñas"
            />

            {reviews.average === null || reviews.count === 0 ? (
                <div className="flex items-center gap-4 rounded-xl p-4 skin-card sm:p-5">
                    <span
                        aria-hidden
                        className="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-text"
                    >
                        <Star className="size-5" />
                    </span>
                    <p className="text-sm text-balance text-muted-foreground">
                        Todavía no hay reseñas. Aparecen cuando alguien opina de
                        una cita atendida.
                    </p>
                </div>
            ) : (
                <>
                    <RatingSummary
                        average={reviews.average}
                        count={reviews.count}
                        note={PUBLIC_REVIEWS_NOTE}
                        onBrand={layout.highlight === 'solid'}
                        className={SUMMARY[layout.highlight]}
                    />
                    {/* Columns, not a grid: comments differ a lot in length. */}
                    <ul
                        className={cn(
                            'gap-3 lg:gap-4',
                            // One review alone fills the row: half a row
                            // beside nothing reads as something missing.
                            shown.length > 1 && 'sm:columns-2',
                        )}
                    >
                        {shown.map((review) => (
                            <li
                                key={review.id}
                                className="mb-3 break-inside-avoid lg:mb-4"
                            >
                                <ReviewCard
                                    review={review}
                                    replyTitle={`Respuesta de ${salonName}`}
                                    className="skin-card"
                                />
                            </li>
                        ))}
                    </ul>
                    {hidden > 0 && (
                        <button
                            type="button"
                            onClick={() => setShowsAll(true)}
                            className={cn(CHIP_ACTION, '-mt-2 self-start')}
                        >
                            Ver más reseñas
                        </button>
                    )}
                </>
            )}
        </section>
    );
}
