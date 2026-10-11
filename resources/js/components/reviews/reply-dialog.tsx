import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import { reply as saveReply } from '@/routes/reviews';
import { CountedTextarea } from './counted-textarea';
import { ReviewQuote } from './review-quote';
import type { OwnerReview } from './types';

type Props = {
    open: boolean;
    onClose: () => void;
    /** Stays while the dialog closes, so its title does not flip. */
    review?: OwnerReview;
    maxLength: number;
};

function ReplyForm({
    review,
    maxLength,
    onSaved,
}: {
    review: OwnerReview;
    maxLength: number;
    onSaved: () => void;
}) {
    const form = useForm({ reply: review.reply ?? '' });
    const isEditing = review.reply !== null;
    const isEmpty = form.data.reply.trim() === '';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(saveReply(review.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onSaved,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-5">
            <ReviewQuote review={review} />
            <CountedTextarea
                id="review-reply"
                label="Tu respuesta"
                value={form.data.reply}
                onChange={(reply) => form.setData('reply', reply)}
                maxLength={maxLength}
                error={form.errors.reply}
                hint={
                    isEditing
                        ? 'Si la dejas vacía y guardas, la respuesta se retira.'
                        : undefined
                }
                placeholder="Agradece, aclara o cuenta qué vas a mejorar"
            />
            <DialogFooter className="gap-2 [&_button]:h-11 sm:[&_button]:h-9">
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Volver
                    </Button>
                </DialogClose>
                <Button
                    type="submit"
                    disabled={form.processing || (isEmpty && !isEditing)}
                >
                    {isEditing && isEmpty
                        ? 'Retirar respuesta'
                        : 'Guardar respuesta'}
                </Button>
            </DialogFooter>
        </form>
    );
}

/**
 * The salon answers a review, or edits its answer (a sheet on a phone).
 * The form lives only while open, so every opening starts from what is saved.
 */
export function ReplyDialog({ open, onClose, review, maxLength }: Props) {
    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className={cn(SHEET_ON_PHONE, 'overflow-y-auto')}>
                <DialogHeader className="pr-8 text-left">
                    <DialogTitle>
                        {review?.reply ? 'Editar respuesta' : 'Responder'}
                    </DialogTitle>
                    <DialogDescription>
                        La lee quien escribió la reseña.
                        {review?.isPublishable &&
                            ' Si tus reseñas son públicas, también quien visita tu página.'}
                    </DialogDescription>
                </DialogHeader>
                {review && (
                    <ReplyForm
                        key={review.id}
                        review={review}
                        maxLength={maxLength}
                        onSaved={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
