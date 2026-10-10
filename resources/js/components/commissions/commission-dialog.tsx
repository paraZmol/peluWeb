import type { ReactNode } from 'react';
import { usePortalTheme } from '@/components/salon/portal-theme';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { cn } from '@/lib/utils';

type Props = {
    open: boolean;
    onOpenChange: (isOpen: boolean) => void;
    /** A `DialogTrigger` with the button that opens it. */
    trigger: ReactNode;
    title: string;
    description: string;
    /** The form: it lives only while open, so every opening starts clean. */
    children: ReactNode;
};

/** Paying commissions or setting their rules (a sheet on a phone). */
export function CommissionDialog({
    open,
    onOpenChange,
    trigger,
    title,
    description,
    children,
}: Props) {
    const portalTheme = usePortalTheme();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {trigger}
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
