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
import { CardPicker } from './card-picker';
import type { PromotionCardKind, PromotionService } from './types';

type Props = {
    open: boolean;
    onClose: () => void;
    services: PromotionService[];
    today: string;
    onPick: (card: PromotionCardKind) => void;
};

/** "Nueva promoción": the cards to start from (a sheet on a phone). */
export function NewPromotionDialog({
    open,
    onClose,
    services,
    today,
    onPick,
}: Props) {
    const portalTheme = usePortalTheme();

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent
                {...portalTheme}
                className={cn(
                    portalTheme.className,
                    SHEET_ON_PHONE,
                    'overflow-y-auto sm:max-w-2xl',
                )}
            >
                <DialogHeader className="pr-8 text-left">
                    <DialogTitle>Nueva promoción</DialogTitle>
                    <DialogDescription>
                        Elige de dónde partir. Después rellenas dos o tres datos
                        y ves cómo queda antes de publicarla.
                    </DialogDescription>
                </DialogHeader>
                <CardPicker services={services} today={today} onPick={onPick} />
            </DialogContent>
        </Dialog>
    );
}
