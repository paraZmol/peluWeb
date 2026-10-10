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
};

/** The receipt's thumbnail; tapping it shows the photo big enough to read. */
export function ReceiptViewer({ url, caption }: Props) {
    const portalTheme = usePortalTheme();
    return (
        <Dialog>
            <DialogTrigger
                aria-label={`Ver comprobante: ${caption}`}
                className="size-11 shrink-0 overflow-hidden rounded-md border outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <img
                    src={url}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover"
                />
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
                    <DialogTitle>Comprobante</DialogTitle>
                    <DialogDescription>{caption}</DialogDescription>
                </DialogHeader>
                <img
                    src={url}
                    alt={`Foto del comprobante: ${caption}`}
                    className="max-h-[70svh] w-full rounded-md bg-muted object-contain"
                />
            </DialogContent>
        </Dialog>
    );
}
