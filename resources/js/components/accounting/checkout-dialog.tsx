import { Banknote, Check } from 'lucide-react';
import type { AgendaBooking } from '@/components/booking/types';
import { usePortalTheme } from '@/components/salon/portal-theme';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTrigger } from '@/components/ui/dialog';
import { toCents } from '@/lib/money';
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import { CheckoutForm } from './checkout-form';

type Props = { booking: AgendaBooking; triggerClassName?: string };

/**
 * The button that ends a visit: it opens the checkout (a sheet on a phone).
 * The form lives only while open, so every opening starts clean.
 */
export function CheckoutDialog({ booking, triggerClassName }: Props) {
    const portalTheme = usePortalTheme();
    const isSettled = toCents(booking.balance) === 0;

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button className={triggerClassName}>
                    {isSettled ? (
                        <>
                            <Check aria-hidden /> Terminé
                        </>
                    ) : (
                        <>
                            <Banknote aria-hidden /> Cobrar
                        </>
                    )}
                </Button>
            </DialogTrigger>
            <DialogContent
                {...portalTheme}
                className={cn(
                    portalTheme.className,
                    SHEET_ON_PHONE,
                    'overflow-y-auto',
                )}
            >
                <CheckoutForm booking={booking} />
            </DialogContent>
        </Dialog>
    );
}
