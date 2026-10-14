import { usePortalTheme } from '@/components/salon/portal-theme';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import type { PromotionTarget } from '@/hooks/use-promotion-form';
import { cn } from '@/lib/utils';
import { LockedPromotionForm } from './locked-promotion-form';
import { PromotionForm } from './promotion-form';
import type { PromotionCardKind, PromotionContext } from './types';

const HEADINGS: Record<PromotionCardKind, { title: string; lead: string }> = {
    discount: {
        title: 'Descuento',
        lead: 'Elige los servicios y cuánto menos. Se aplica solo al reservar, sin códigos.',
    },
    gift: {
        title: 'Servicio de regalo',
        lead: 'Quien reserve uno de tus servicios se lleva otro sin costo en la misma cita.',
    },
    quiet_hours: {
        title: 'Horas flojas',
        lead: 'Un descuento que solo vale en los días y horas que quieres llenar.',
    },
    season: {
        title: 'Campaña de temporada',
        lead: 'Elige la ocasión: ponemos su nombre y sus fechas, y tú decides qué ofreces.',
    },
    first_visit: {
        title: 'Primera visita',
        lead: 'Un descuento solo para quien nunca tuvo una cita en tu salón. Sirve para atraer clientes nuevos.',
    },
};

type Props = {
    open: boolean;
    onClose: () => void;
    /** Stays while the sheet closes, so its content does not flip. */
    target?: PromotionTarget;
    context: PromotionContext;
};

/**
 * Where a promotion is filled in: beside the list on a wide screen, a
 * sheet from the bottom on a phone. The form lives only while open.
 */
export function PromotionSheet({ open, onClose, target, context }: Props) {
    const portalTheme = usePortalTheme();
    const isMobile = useIsMobile();
    const promotion = target?.promotion;

    return (
        <Sheet open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <SheetContent
                {...portalTheme}
                side={isMobile ? 'bottom' : 'right'}
                className={cn(
                    portalTheme.className,
                    'gap-0 text-foreground',
                    isMobile
                        ? 'h-[94svh] rounded-t-2xl'
                        : 'w-full sm:max-w-4xl',
                )}
            >
                {target && (
                    <>
                        <SheetHeader className="px-4 pr-12 sm:px-6">
                            <SheetTitle className="font-display text-2xl">
                                {promotion
                                    ? 'Editar promoción'
                                    : HEADINGS[target.card].title}
                            </SheetTitle>
                            <SheetDescription>
                                {promotion
                                    ? promotion.name
                                    : HEADINGS[target.card].lead}
                            </SheetDescription>
                        </SheetHeader>
                        {promotion?.isLocked ? (
                            <LockedPromotionForm
                                key={promotion.id}
                                promotion={promotion}
                                context={context}
                                onSaved={onClose}
                            />
                        ) : (
                            <PromotionForm
                                key={promotion?.id ?? target.card}
                                target={target}
                                context={context}
                                onSaved={onClose}
                            />
                        )}
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
