import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { starsLabel } from '@/lib/reviews';
import { destroy } from '@/routes/bookings/review';
import { SalonReply } from './salon-reply';
import { StarRating } from './star-rating';
import type { MyReview as MyReviewData } from './types';

type Props = {
    code: string;
    salonName: string;
    review: MyReviewData;
};

/**
 * The review the person left for this visit, with the salon's answer, and
 * the way to take it back (for good: the visit cannot be reviewed again,
 * so it asks first).
 */
export function MyReview({ code, salonName, review }: Props) {
    return (
        <section
            id="resena"
            aria-labelledby="my-review-title"
            className="flex scroll-mt-6 flex-col gap-4 rounded-xl p-4 skin-card sm:p-6"
        >
            <div className="flex flex-col gap-2">
                <h2
                    id="my-review-title"
                    className="font-sans text-base font-bold"
                >
                    Tu reseña
                </h2>
                <div className="flex items-center gap-2.5">
                    <StarRating value={review.rating} size="lg" />
                    <span className="text-sm font-semibold tabular-nums">
                        {starsLabel(review.rating)}
                    </span>
                </div>
            </div>

            {review.comment && (
                <p className="break-words whitespace-pre-line">
                    {review.comment}
                </p>
            )}

            {review.reply && (
                <SalonReply
                    title={`Respuesta de ${salonName}`}
                    text={review.reply}
                />
            )}

            <div className="border-t pt-2">
                <ConfirmRemoveDialog
                    themed
                    triggerLabel="Retirar mi reseña"
                    trigger={
                        <button
                            type="button"
                            className="inline-flex min-h-11 items-center rounded-md text-sm font-medium text-muted-foreground underline underline-offset-4 outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            Retirar mi reseña
                        </button>
                    }
                    title="¿Retirar tu reseña?"
                    description="Se borra y no podrás volver a opinar de esta cita."
                    confirmLabel="Retirar"
                    form={destroy.form(code)}
                />
            </div>
        </section>
    );
}
