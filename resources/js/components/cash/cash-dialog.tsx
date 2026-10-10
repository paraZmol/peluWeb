import type { ReactNode } from 'react';
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
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { cn } from '@/lib/utils';

type Props = {
    trigger: ReactNode;
    title: string;
    description: string;
    /** The form: it lives only while open, so every opening starts clean. */
    children: ReactNode;
};

/** Opening or closing the till (a sheet on a phone). */
export function CashDialog({ trigger, title, description, children }: Props) {
    const portalTheme = usePortalTheme();

    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent
                {...portalTheme}
                className={cn(
                    portalTheme.className,
                    SHEET_ON_PHONE,
                    'overflow-y-auto',
                )}
            >
                <DialogHeader className="pr-8 text-left">
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}

type FooterProps = { confirmLabel: string; processing: boolean };

/** "Volver" and the confirmation, with the thumb-sized buttons of a sheet. */
export function CashDialogFooter({ confirmLabel, processing }: FooterProps) {
    return (
        <DialogFooter className="gap-2 [&_button]:h-12 sm:[&_button]:h-10">
            <DialogClose asChild>
                <Button type="button" variant="outline">
                    Volver
                </Button>
            </DialogClose>
            <Button type="submit" disabled={processing}>
                {processing && <Spinner />}
                {confirmLabel}
            </Button>
        </DialogFooter>
    );
}
