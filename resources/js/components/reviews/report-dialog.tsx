import { useForm } from '@inertiajs/react';
import { useState } from 'react';
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
import { isReportReasonValid, REPORT_MIN_LENGTH } from '@/lib/reviews';
import { SHEET_ON_PHONE, TOUCH_FOOTER } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import { report } from '@/routes/reviews';
import { CountedTextarea } from './counted-textarea';
import { NetworkProblem } from './network-problem';
import { ReviewQuote } from './review-quote';
import type { OwnerReview } from './types';

type Props = {
    open: boolean;
    onClose: () => void;
    /** Stays while the dialog closes, so its content does not vanish. */
    review?: OwnerReview;
    maxLength: number;
};

function ReportForm({
    review,
    maxLength,
    onSent,
}: {
    review: OwnerReview;
    maxLength: number;
    onSent: () => void;
}) {
    const form = useForm({ reason: '' });
    const [hasNetworkProblem, setHasNetworkProblem] = useState(false);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(report(review.id), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setHasNetworkProblem(false),
            onNetworkError: () => setHasNetworkProblem(true),
            onSuccess: onSent,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-5">
            <ReviewQuote review={review} />
            <CountedTextarea
                id="review-report-reason"
                label="Motivo"
                value={form.data.reason}
                onChange={(reason) => form.setData('reason', reason)}
                maxLength={maxLength}
                error={form.errors.reason}
                hint={`Cuenta qué pasa, con al menos ${REPORT_MIN_LENGTH} caracteres.`}
                isRequired
            />
            <NetworkProblem isShown={hasNetworkProblem} />
            <DialogFooter className={TOUCH_FOOTER}>
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Volver
                    </Button>
                </DialogClose>
                <Button
                    type="submit"
                    disabled={
                        form.processing ||
                        !isReportReasonValid(form.data.reason, maxLength)
                    }
                >
                    Reportar
                </Button>
            </DialogFooter>
        </form>
    );
}

/**
 * The salon asks the platform to look at a review. Only the platform takes
 * one down, and never for being negative: the dialog says so before sending.
 */
export function ReportDialog({ open, onClose, review, maxLength }: Props) {
    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className={cn(SHEET_ON_PHONE, 'overflow-y-auto')}>
                <DialogHeader className="pr-8 text-left">
                    <DialogTitle>Reportar reseña</DialogTitle>
                    <DialogDescription>
                        El equipo de peluWeb la revisa. No se oculta por ser
                        negativa: solo si insulta, revela datos personales o no
                        trata de la cita.
                    </DialogDescription>
                </DialogHeader>
                {review && (
                    <ReportForm
                        key={review.id}
                        review={review}
                        maxLength={maxLength}
                        onSent={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
