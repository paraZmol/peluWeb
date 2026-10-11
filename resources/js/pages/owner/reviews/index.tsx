import { Head } from '@inertiajs/react';
import { MessageSquareText } from 'lucide-react';
import { useState } from 'react';
import { ModePicker } from '@/components/reviews/mode-picker';
import { OwnerReviewList } from '@/components/reviews/owner-review-list';
import { RatingSummary } from '@/components/reviews/rating-summary';
import { ReplyDialog } from '@/components/reviews/reply-dialog';
import { ReportDialog } from '@/components/reviews/report-dialog';
import type {
    OwnerReview,
    OwnerReviews,
    ReviewLimits,
    ReviewMode,
    ReviewModeOption,
} from '@/components/reviews/types';
import { emptyReviewsMessage } from '@/lib/reviews';
import { index } from '@/routes/reviews';

type Props = {
    mode: ReviewMode;
    modes: ReviewModeOption[];
    reviews: OwnerReviews;
    limits: ReviewLimits;
};

/** Closed, answering a review, or reporting one. */
type DialogState = {
    kind: 'reply' | 'report' | null;
    review?: OwnerReview;
};

/**
 * The salon's reviews: who reads them, the average, and each one with its
 * answer. Phones: one column. Desktop: the average stays beside the list.
 */
export default function Reviews({ mode, modes, reviews, limits }: Props) {
    // The review stays while the dialog closes, so its content does not flip.
    const [dialog, setDialog] = useState<DialogState>({ kind: null });
    const close = () => setDialog({ ...dialog, kind: null });

    return (
        <>
            <Head title="Reseñas" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6">
                <div>
                    <h1 className="text-2xl font-semibold">Reseñas</h1>
                    <p className="text-sm text-muted-foreground">
                        Lo que opinan tus clientes después de una cita atendida.
                        Puedes responder y reportar; no puedes editarlas ni
                        borrarlas. Quien la escribió sí puede retirarla.
                    </p>
                </div>

                <ModePicker mode={mode} modes={modes} />

                {reviews.items.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center">
                        <MessageSquareText
                            className="size-6 text-muted-foreground"
                            aria-hidden
                        />
                        <p className="max-w-sm text-balance text-muted-foreground">
                            {emptyReviewsMessage(mode)}
                        </p>
                    </div>
                ) : (
                    <div className="grid items-start gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
                        {reviews.average !== null && (
                            <section
                                aria-label="Resumen"
                                className="lg:sticky lg:top-6"
                            >
                                <RatingSummary
                                    average={reviews.average}
                                    count={reviews.count}
                                    note={
                                        mode === 'public'
                                            ? 'De todas tus reseñas, también las privadas.'
                                            : 'Solo lo ve tu equipo.'
                                    }
                                    className="border bg-card shadow-card lg:flex-col lg:items-start"
                                />
                            </section>
                        )}
                        <div className="lg:col-start-2">
                            <OwnerReviewList
                                reviews={reviews.items}
                                onReply={(review) =>
                                    setDialog({ kind: 'reply', review })
                                }
                                onReport={(review) =>
                                    setDialog({ kind: 'report', review })
                                }
                            />
                        </div>
                    </div>
                )}
            </div>

            <ReplyDialog
                open={dialog.kind === 'reply'}
                onClose={close}
                review={dialog.review}
                maxLength={limits.reply}
            />
            <ReportDialog
                open={dialog.kind === 'report'}
                onClose={close}
                review={dialog.review}
                maxLength={limits.report}
            />
        </>
    );
}

Reviews.layout = {
    breadcrumbs: [{ title: 'Reseñas', href: index() }],
};
