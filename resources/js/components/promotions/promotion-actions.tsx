import { router } from '@inertiajs/react';
import { Copy, Pause, Pencil, Play, Share2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import { TOUCH_FOOTER } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import { destroy, duplicate, pause, publish } from '@/routes/promotions';
import type { Promotion, PromotionState } from './types';

/** What can be done from the list in each state. */
const SHAREABLE: PromotionState[] = ['scheduled', 'active', 'paused'];
const PAUSABLE: PromotionState[] = ['scheduled', 'active'];
const PUBLISHABLE: PromotionState[] = ['draft', 'paused'];

type Props = {
    promotion: Promotion;
    onEdit: () => void;
    onShare: () => void;
};

/** The buttons under a promotion. The server decides again on every one. */
export function PromotionActions({ promotion, onEdit, onShare }: Props) {
    const [isBusy, setIsBusy] = useState(false);
    const { id, state } = promotion;
    const act = (url: string) =>
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setIsBusy(true),
                onFinish: () => setIsBusy(false),
            },
        );

    return (
        <div className={cn('flex flex-wrap border-t pt-3', TOUCH_FOOTER)}>
            {SHAREABLE.includes(state) && (
                <Button type="button" variant="outline" onClick={onShare}>
                    <Share2 aria-hidden /> Compartir
                </Button>
            )}
            {PUBLISHABLE.includes(state) && (
                <Button
                    type="button"
                    disabled={isBusy}
                    onClick={() => act(publish.url(id))}
                >
                    <Play aria-hidden /> Publicar
                </Button>
            )}
            {state !== 'ended' && (
                <Button type="button" variant="ghost" onClick={onEdit}>
                    <Pencil aria-hidden /> Editar
                </Button>
            )}
            {PAUSABLE.includes(state) && (
                <Button
                    type="button"
                    variant="ghost"
                    disabled={isBusy}
                    onClick={() => act(pause.url(id))}
                >
                    <Pause aria-hidden /> Pausar
                </Button>
            )}
            <Button
                type="button"
                variant="ghost"
                disabled={isBusy}
                onClick={() => act(duplicate.url(id))}
            >
                <Copy aria-hidden /> Duplicar
            </Button>
            {state === 'draft' && (
                <ConfirmRemoveDialog
                    triggerLabel="Eliminar"
                    trigger={
                        <Button
                            type="button"
                            variant="ghost"
                            className="text-destructive-foreground"
                        >
                            <Trash2 aria-hidden /> Eliminar
                        </Button>
                    }
                    title="¿Eliminar este borrador?"
                    description={`${promotion.name}. Nadie lo ha visto todavía: se borra del todo.`}
                    confirmLabel="Sí, eliminar"
                    themed
                    form={destroy.form(id)}
                />
            )}
        </div>
    );
}
