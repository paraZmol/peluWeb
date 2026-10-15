import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SHEET_ON_PHONE } from '@/lib/sheet';

type Props = {
    open: boolean;
    isSaving: boolean;
    onClose: () => void;
    onConfirm: () => void;
};

/**
 * Asked before a salon shows its reviews: every one it gets from then on
 * goes out, the bad ones too, and none can be hidden one by one (PLAN 4.8).
 */
export function PublicModeDialog({
    open,
    isSaving,
    onClose,
    onConfirm,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className={SHEET_ON_PHONE}>
                <DialogHeader className="pr-8 text-left">
                    <DialogTitle>¿Hacer públicas tus reseñas?</DialogTitle>
                    <DialogDescription>
                        En modo público se muestran todas las reseñas que
                        recibas desde ahora, también las negativas. No se pueden
                        ocultar una por una. Las que ya recibiste en privado
                        siguen siendo privadas. Puedes volver a Privadas cuando
                        quieras.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 [&_button]:h-11 sm:[&_button]:h-9">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Volver
                    </Button>
                    <Button
                        type="button"
                        disabled={isSaving}
                        onClick={onConfirm}
                    >
                        Hacer públicas
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
