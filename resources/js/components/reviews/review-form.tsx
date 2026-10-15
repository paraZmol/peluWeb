import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import {
    COMMENT_MAX_LENGTH,
    isRatingChosen,
    RATING_REQUIRED,
    starsLabel,
} from '@/lib/reviews';
import { cn } from '@/lib/utils';
import { store } from '@/routes/bookings/review';
import { CountedTextarea } from './counted-textarea';
import { ReviewSubmit } from './review-submit';
import { StarRating } from './star-rating';

type Props = {
    code: string;
    salonName: string;
    staffName: string;
    /** The salon shows its reviews: this one goes out with the first name. */
    isPublic: boolean;
};

/**
 * "¿Cómo te fue?": the stars, an optional comment and, right above the
 * button, who is going to read it. Sent without stars, it says so and takes
 * the person back to them.
 */
export function ReviewForm({ code, salonName, staffName, isPublic }: Props) {
    const form = useForm({ rating: 0, comment: '' });
    const { data, setData, errors, processing } = form;
    // The request never arrived: what was written stays to retry.
    const [hasNetworkProblem, setHasNetworkProblem] = useState(false);
    const hasRating = isRatingChosen(data.rating);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!hasRating) {
            form.setError('rating', RATING_REQUIRED);
            event.currentTarget
                .querySelector<HTMLInputElement>('input[name="rating"]')
                ?.focus();

            return;
        }

        form.submit(store(code), {
            preserveScroll: true,
            onStart: () => setHasNetworkProblem(false),
            onNetworkError: () => setHasNetworkProblem(true),
        });
    };

    return (
        <section
            id="resena"
            aria-labelledby="review-form-title"
            className="scroll-mt-6 rounded-xl p-4 skin-raised sm:p-6"
        >
            <form onSubmit={submit} noValidate className="flex flex-col gap-5">
                <div className="flex flex-col gap-1">
                    <h2
                        id="review-form-title"
                        className="display-type text-title-3"
                    >
                        ¿Cómo te fue?
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Califica tu cita con {staffName} en {salonName}.
                    </p>
                </div>

                <div className="flex flex-col gap-1">
                    <StarRating
                        value={data.rating}
                        onChange={(rating) => {
                            setData('rating', rating);
                            form.clearErrors('rating');
                        }}
                        describedBy={cn(
                            'review-rating-hint',
                            errors.rating && 'review-rating-error',
                        )}
                        isInvalid={Boolean(errors.rating)}
                        className="-ml-1.5"
                    />
                    <p
                        id="review-rating-hint"
                        aria-live="polite"
                        className={cn(
                            'text-sm',
                            hasRating
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        {hasRating
                            ? starsLabel(data.rating)
                            : 'Toca una estrella para calificar.'}
                    </p>
                    <InputError
                        id="review-rating-error"
                        role="alert"
                        message={errors.rating}
                    />
                </div>

                <CountedTextarea
                    id="review-comment"
                    label={
                        <>
                            Comentario{' '}
                            <span className="font-normal text-muted-foreground">
                                (opcional)
                            </span>
                        </>
                    }
                    value={data.comment}
                    onChange={(comment) => setData('comment', comment)}
                    maxLength={COMMENT_MAX_LENGTH}
                    error={errors.comment}
                    placeholder="Qué te gustó, qué mejorarías"
                />

                <ReviewSubmit
                    salonName={salonName}
                    isPublic={isPublic}
                    processing={processing}
                    hasNetworkProblem={hasNetworkProblem}
                />
            </form>
        </section>
    );
}
