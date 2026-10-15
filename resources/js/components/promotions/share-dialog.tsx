import { Check, Instagram, Link2, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { usePortalTheme } from '@/components/salon/portal-theme';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useClipboard } from '@/hooks/use-clipboard';
import { fullTitle, instagramText, whatsappShareUrl } from '@/lib/promotions';
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import type { SalonTheme } from '@/types';
import { PosterButton } from './poster-button';
import type { Promotion, PromotionContext } from './types';

type Copied = 'link' | 'instagram' | 'failed' | null;

type Props = {
    open: boolean;
    onClose: () => void;
    /** Stays while the dialog closes, so its title does not flip. */
    promotion?: Promotion;
    /** For the image: the services, the team and the salon's name. */
    context: PromotionContext;
    theme?: SalonTheme;
};

/** Four ways to take a promotion to where the clients are. */
export function ShareDialog({
    open,
    onClose,
    promotion,
    context,
    theme,
}: Props) {
    const portalTheme = usePortalTheme();
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState<Copied>(null);
    const copyAs = async (kind: 'link' | 'instagram', text: string) =>
        setCopied((await copy(text)) ? kind : 'failed');
    const close = () => {
        setCopied(null);
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && close()}>
            <DialogContent
                {...portalTheme}
                className={cn(portalTheme.className, SHEET_ON_PHONE)}
            >
                <DialogHeader className="pr-8 text-left">
                    <DialogTitle>Compartir promoción</DialogTitle>
                    <DialogDescription>
                        {promotion && fullTitle(promotion)}
                    </DialogDescription>
                </DialogHeader>
                {promotion && (
                    <div className="flex flex-col gap-2 [&_a]:h-12 [&_button]:h-12">
                        <p className="rounded-lg bg-band px-3 py-2 text-sm break-all text-muted-foreground">
                            {promotion.shareUrl}
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => copyAs('link', promotion.shareUrl)}
                        >
                            {copied === 'link' ? (
                                <Check aria-hidden />
                            ) : (
                                <Link2 aria-hidden />
                            )}
                            {copied === 'link'
                                ? 'Enlace copiado'
                                : 'Copiar enlace'}
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={whatsappShareUrl(promotion)}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <MessageCircle aria-hidden /> Enviar por
                                WhatsApp
                            </a>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                copyAs('instagram', instagramText(promotion))
                            }
                        >
                            {copied === 'instagram' ? (
                                <Check aria-hidden />
                            ) : (
                                <Instagram aria-hidden />
                            )}
                            {copied === 'instagram'
                                ? 'Texto copiado'
                                : 'Copiar texto para Instagram'}
                        </Button>
                        <PosterButton
                            promotion={promotion}
                            context={context}
                            theme={theme}
                        />
                        <p role="status" className="min-h-5 text-sm">
                            {copied === 'failed' && (
                                <span className="text-destructive-foreground">
                                    No se pudo copiar. Mantén pulsado el enlace
                                    de arriba para copiarlo.
                                </span>
                            )}
                            {(copied === 'link' || copied === 'instagram') &&
                                'Copiado. Pégalo donde quieras compartirlo.'}
                        </p>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
