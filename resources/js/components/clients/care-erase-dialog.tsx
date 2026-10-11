import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { SHEET_ON_PHONE } from '@/lib/sheet';

type Props = {
    open: boolean;
    clientName: string;
    processing: boolean;
    onConfirm: () => void;
    onClose: () => void;
};

/** Asks before a save that erases the care notes: there is no way back. */
export function CareEraseDialog({
    open,
    clientName,
    processing,
    onConfirm,
    onClose,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent className={SHEET_ON_PHONE}>
                <DialogHeader>
                    <DialogTitle>
                        ¿Retirar la autorización de {clientName}?
                    </DialogTitle>
                    <DialogDescription>
                        Hazlo solo si {clientName} lo pidió o si la hoja firmada
                        no existe. Se borrará lo anotado en «Alergias y cuidado»
                        y no se puede recuperar desde la ficha. Conserva la hoja
                        y anota en ella la fecha del retiro. Para volver a
                        anotarlo hará falta una nueva autorización firmada.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 [&_button]:h-11 sm:[&_button]:h-9">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Volver
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing}
                        onClick={onConfirm}
                    >
                        {processing && <Spinner />}
                        Retirar y borrar alergias
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
