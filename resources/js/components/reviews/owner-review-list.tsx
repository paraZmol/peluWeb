import { Check, Clock, EyeOff, Flag, Lock, MessageSquare } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { canReport, PRIVATE_REVIEW_NOTE, reviewStatus } from '@/lib/reviews';
import type { ReviewStatus } from '@/lib/reviews';
import { cn } from '@/lib/utils';
import { ReviewCard } from './review-card';
import type { OwnerReview } from './types';

const STATUS: Record<ReviewStatus['key'], { icon: LucideIcon; style: string }> =
    {
        reported: { icon: Clock, style: 'bg-warning/15 text-warning' },
        hidden: {
            icon: EyeOff,
            style: 'bg-destructive/10 text-destructive-foreground',
        },
        kept: { icon: Check, style: 'bg-muted text-muted-foreground' },
    };

type TagProps = { icon: LucideIcon; label: string; style: string };

/** The label always says it; color only reinforces it. */
function Tag({ icon: Icon, label, style }: TagProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                style,
            )}
        >
            <Icon className="size-3.5" aria-hidden />
            {label}
        </span>
    );
}

type Props = {
    reviews: OwnerReview[];
    onReply: (review: OwnerReview) => void;
    onReport: (review: OwnerReview) => void;
};

/**
 * Every review of the salon, newest first. The salon answers and reports;
 * it never edits or erases one (PLAN 4.8), so there is no such button.
 */
export function OwnerReviewList({ reviews, onReply, onReport }: Props) {
    return (
        <ul className="flex flex-col gap-3">
            {reviews.map((review) => {
                const status = reviewStatus(review);

                return (
                    <li key={review.id}>
                        <ReviewCard
                            review={review}
                            replyTitle="Tu respuesta"
                            detail={`Atendió ${review.staffName}`}
                            status={
                                <>
                                    {!review.isPublishable && (
                                        <Tag
                                            icon={Lock}
                                            label="Privada"
                                            style="bg-muted text-muted-foreground"
                                        />
                                    )}
                                    {status && (
                                        <Tag
                                            label={status.label}
                                            {...STATUS[status.key]}
                                        />
                                    )}
                                </>
                            }
                            note={
                                review.isPublishable
                                    ? undefined
                                    : PRIVATE_REVIEW_NOTE
                            }
                            className="border bg-card shadow-card"
                            actions={
                                <div className="flex flex-wrap gap-2 border-t pt-3 [&_button]:h-11 sm:[&_button]:h-9">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => onReply(review)}
                                    >
                                        <MessageSquare aria-hidden />
                                        {review.reply
                                            ? 'Editar respuesta'
                                            : 'Responder'}
                                    </Button>
                                    {canReport(review) && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => onReport(review)}
                                        >
                                            <Flag aria-hidden />
                                            Reportar
                                        </Button>
                                    )}
                                </div>
                            }
                        />
                    </li>
                );
            })}
        </ul>
    );
}
