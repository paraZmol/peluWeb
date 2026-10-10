import type { ReactNode } from 'react';
import { usePortalTheme } from '@/components/salon/portal-theme';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { cn } from '@/lib/utils';

type Props = {
    url: string;
    /** What the receipt is of, e.g. "Alquiler · S/ 1200.50 · mié 7 oct". */
    caption: string;
    /** For photos that are not receipts: what to call them, e.g. "foto". */
    noun?: string;
    /** Describes the photo itself when the thumbnail is content, not decoration. */
    alt?: string;
    /** The thumbnail's own size and shape instead of the 44 px square. */
    className?: string;
    /** Drawn over the thumbnail, e.g. a label. */
    children?: ReactNode;
};

/** The receipt's thumbnail; tapping it shows the photo big enough to read. */
export function ReceiptViewer({
    url,
    caption,
    noun = 'comprobante',
    alt,
    className,
    children,
}: Props) {
    const portalTheme = usePortalTheme();
    const title = noun.charAt(0).toUpperCase() + noun.slice(1);

    return (
        <Dialog>
            <DialogTrigger
                aria-label={`Ver ${noun}: ${caption}`}
                className={cn(
                    'size-11 shrink-0 overflow-hidden rounded-md border outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    className,
                )}
            >
                <img
                    src={url}
                    alt={alt ?? ''}
                    loading="lazy"
                    className="size-full object-cover"
                />
                {children}
            </DialogTrigger>
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
                    <DialogDescription>{caption}</DialogDescription>
                </DialogHeader>
                <img
                    src={url}
                    alt={alt ?? `Foto del comprobante: ${caption}`}
                    className="max-h-[70svh] w-full rounded-md bg-muted object-contain"
                />
            </DialogContent>
        </Dialog>
    );
}
