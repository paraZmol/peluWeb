import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
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
import { PhotoUpload } from './photo-upload';

type Props = {
    clientId: number;
    clientName: string;
    /** The file already keeps all the pictures it may. */
    isFull: boolean;
    /** How many pictures a file keeps at most. */
    max: number;
};

/** "Agregar foto" and the form it opens; switched off on a full file. */
export function PhotoAddDialog({ clientId, clientName, isFull, max }: Props) {
    const [isAdding, setIsAdding] = useState(false);

    return (
        <>
            <Dialog open={isAdding} onOpenChange={setIsAdding}>
                <DialogTrigger asChild>
                    <Button
                        disabled={isFull}
                        aria-describedby={
                            isFull ? 'client-photos-full' : undefined
                        }
                        className="h-12 disabled:bg-muted disabled:text-muted-foreground disabled:opacity-100 sm:h-10 sm:self-start"
                    >
                        <Plus aria-hidden /> Agregar foto
                    </Button>
                </DialogTrigger>
                <DialogContent
                    className={cn(SHEET_ON_PHONE, 'overflow-y-auto')}
                >
                    <DialogHeader className="pr-8 text-left">
                        <DialogTitle>Agregar foto</DialogTitle>
                        <DialogDescription>
                            A la ficha de {clientName}. Solo para el registro
                            interno del salón.
                        </DialogDescription>
                    </DialogHeader>
                    {/* Lives only while open: every upload starts unchecked. */}
                    <PhotoUpload
                        clientId={clientId}
                        clientName={clientName}
                        onSaved={() => setIsAdding(false)}
                    />
                </DialogContent>
            </Dialog>
            {isFull && (
                <p id="client-photos-full" className="text-sm">
                    Esta ficha ya tiene sus {max} fotos. Elimina alguna para
                    subir otra.
                </p>
            )}
        </>
    );
}
