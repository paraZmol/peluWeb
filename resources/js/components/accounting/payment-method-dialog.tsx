import { router } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { usePortalTheme } from '@/components/salon/portal-theme';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { usePaymentMethods } from '@/hooks/use-checkout';
import { formatPrice } from '@/lib/format';
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import { PaymentMethodChips } from './payment-method-chips';

type Props = {
    trigger: ReactNode;
    title: string;
    description: string;
    /** Soles as text; none when no money changes hands (no method asked). */
    amount: string | null;
    /** The question above the methods. */
    legend: string;
    defaultMethod: string;
    confirmLabel: string;
    /** Where the chosen method is posted. */
    url: string;
};

/**
 * A fixed amount that came in or went out: only the method is chosen. Like
 * the checkout, it leaves when the booking stops offering the action.
 */
export function PaymentMethodDialog({
    trigger,
    title,
    description,
    amount,
    legend,
    defaultMethod,
    confirmLabel,
    url,
}: Props) {
    const portalTheme = usePortalTheme();
    const methods = usePaymentMethods();
    const [method, setMethod] = useState(defaultMethod);
    const [sending, setSending] = useState(false);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.post(url, amount === null ? {} : { method }, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setSending(true),
            onFinish: () => setSending(false),
        });
    };

    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent
                {...portalTheme}
                className={cn(portalTheme.className, SHEET_ON_PHONE)}
            >
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader className="pr-8 text-left">
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {amount !== null && (
                        <>
                            <p className="text-3xl font-semibold tabular-nums">
                                {formatPrice(amount)}
                            </p>
                            <PaymentMethodChips
                                legend={legend}
                                methods={methods}
                                value={method}
                                onChange={setMethod}
                            />
                        </>
                    )}
                    <DialogFooter className="gap-2 [&_button]:h-12 sm:[&_button]:h-10">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Volver
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={sending}>
                            {sending && <Spinner />}
                            {confirmLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
