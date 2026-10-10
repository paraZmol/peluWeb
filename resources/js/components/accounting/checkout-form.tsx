import { router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useId, useState } from 'react';
import type { AgendaBooking } from '@/components/booking/types';
import { Button } from '@/components/ui/button';
import {
    DialogClose,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useCheckout, usePaymentMethods } from '@/hooks/use-checkout';
import { formatCents } from '@/lib/format';
import { complete } from '@/routes/agenda/bookings';
import { CheckoutDiscount } from './checkout-discount';
import { CheckoutSummary } from './checkout-summary';
import { PaymentMethodChips } from './payment-method-chips';
import { SplitPayments } from './split-payments';

type Props = { booking: AgendaBooking };

/**
 * Ends a visit and records how it was paid. It never closes itself: once
 * charged, the booking no longer offers the action and the dialog leaves
 * with it; if the server refuses, it stays open with what was typed.
 */
export function CheckoutForm({ booking }: Props) {
    const methods = usePaymentMethods();
    const checkout = useCheckout(booking.balance, methods);
    const [sending, setSending] = useState(false);
    const problemId = useId();
    const nothingToPay = checkout.totalCents === 0;
    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (checkout.problem || sending) {
            return;
        }

        router.post(complete.url(booking.id), checkout.payload(), {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setSending(true),
            onFinish: () => setSending(false),
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <DialogHeader className="pr-8 text-left">
                <DialogTitle>Cobrar</DialogTitle>
                <DialogDescription>
                    Cita de {booking.client.name} con {booking.staffName}.
                </DialogDescription>
            </DialogHeader>

            <CheckoutSummary
                booking={booking}
                discountCents={checkout.discountCents}
                totalCents={checkout.totalCents}
            />
            {booking.canDiscount && (
                <CheckoutDiscount
                    discount={checkout.discount}
                    onDiscount={checkout.setDiscount}
                />
            )}

            {!nothingToPay && !checkout.isSplit && (
                <>
                    <PaymentMethodChips
                        legend="¿Con qué paga?"
                        methods={methods}
                        value={checkout.rows[0].method}
                        onChange={(method) => checkout.setMethod(0, method)}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        className="-ml-4 h-11 self-start md:h-11"
                        onClick={checkout.split}
                    >
                        Dividir el pago
                    </Button>
                </>
            )}
            {!nothingToPay && checkout.isSplit && (
                <SplitPayments
                    rows={checkout.rows}
                    canAdd={checkout.canAddPart}
                    onMethod={checkout.setMethod}
                    onAmount={checkout.setAmount}
                    onAdd={checkout.addPart}
                    onRemove={checkout.removePart}
                />
            )}

            <p
                id={problemId}
                role="status"
                className="min-h-5 text-sm font-medium text-destructive-foreground"
            >
                {checkout.problem}
            </p>

            <DialogFooter className="gap-2 [&_button]:h-12 sm:[&_button]:h-10">
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Volver
                    </Button>
                </DialogClose>
                <Button
                    type="submit"
                    disabled={checkout.problem !== null || sending}
                    aria-describedby={problemId}
                    // Switched off it must still read as a button, not as a gap.
                    className="tabular-nums disabled:bg-muted disabled:text-muted-foreground disabled:opacity-100 sm:min-w-40"
                >
                    {sending && <Spinner />}
                    {nothingToPay
                        ? 'Completar'
                        : `Cobrar ${formatCents(checkout.totalCents)}`}
                </Button>
            </DialogFooter>
        </form>
    );
}
