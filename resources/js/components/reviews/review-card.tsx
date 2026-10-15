import type { ReactNode } from 'react';
import { formatLongDay } from '@/lib/clients';
import { authorInitial } from '@/lib/reviews';
import { cn } from '@/lib/utils';
import { SalonReply } from './salon-reply';
import { StarRating } from './star-rating';
import type { PublicReview } from './types';

type Props = {
    review: PublicReview;
    /** "Respuesta de {Salón}" on the public page, "Tu respuesta" in the panel. */
    replyTitle: string;
    /** Under the date: who attended (panel only). */
    detail?: string;
    /** Beside the author: where a report stands (panel only). */
    status?: ReactNode;
    /** Under the comment: something the team must know about it (panel only). */
    note?: string;
    /** Under everything: what the salon can do with it (panel only). */
    actions?: ReactNode;
    /** Surface of the card: a skin on a salon's page, a plain card in the panel. */
    className?: string;
};

/**
 * One review: who (first name only), when, the stars, the comment as plain
 * text and the salon's answer set in below it.
 */
export function ReviewCard({
    review,
    replyTitle,
    detail,
    status,
    note,
    actions,
    className,
}: Props) {
    return (
        <article
            aria-label={`Reseña de ${review.author}`}
            className={cn(
                'flex flex-col gap-3 rounded-xl p-4 sm:p-5',
                className,
            )}
        >
            <header className="flex items-start gap-3">
                <span
                    aria-hidden
                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-soft font-bold text-brand-text"
                >
                    {authorInitial(review.author)}
                </span>
                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <p className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="min-w-0 font-semibold break-words">
                            {review.author}
                        </span>
                        {status}
                    </p>
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {formatLongDay(review.date)}
                        {detail && ` · ${detail}`}
                    </p>
                </div>
            </header>

            <StarRating value={review.rating} />

            {review.comment && (
                <p className="break-words whitespace-pre-line">
                    {review.comment}
                </p>
            )}

            {note && <p className="text-sm text-muted-foreground">{note}</p>}

            {review.reply && (
                <SalonReply
                    title={replyTitle}
                    text={review.reply}
                    repliedOn={review.repliedOn}
                />
            )}

            {actions}
        </article>
    );
}
