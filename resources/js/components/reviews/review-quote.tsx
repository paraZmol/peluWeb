import { StarRating } from './star-rating';
import type { PublicReview } from './types';

/** The review a dialog is about, kept in sight while answering or reporting. */
export function ReviewQuote({ review }: { review: PublicReview }) {
    return (
        <blockquote className="flex flex-col gap-1.5 rounded-lg bg-muted p-3 text-sm">
            <p className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span className="font-semibold">{review.author}</span>
                <StarRating value={review.rating} size="sm" />
            </p>
            {review.comment && (
                <p className="line-clamp-4 break-words whitespace-pre-line text-muted-foreground">
                    {review.comment}
                </p>
            )}
        </blockquote>
    );
}
