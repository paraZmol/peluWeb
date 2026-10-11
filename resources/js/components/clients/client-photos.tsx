import { Images, Plus } from 'lucide-react';
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
import { PhotoGrid } from './photo-grid';
import { PhotoUpload } from './photo-upload';
import type { ClientPhoto } from './types';

type Props = {
    clientId: number;
    clientName: string;
    photos: ClientPhoto[];
    /** How many pictures a file keeps at most. */
    max: number;
    className?: string;
};

/** Before and after pictures of the work, for the salon's own record. */
export function ClientPhotos({
    clientId,
    clientName,
    photos,
    max,
    className,
}: Props) {
    const [isAdding, setIsAdding] = useState(false);
    const isFull = photos.length >= max;

    return (
        <section
            aria-labelledby="client-photos-title"
            className={cn(
                'flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-card',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2
                        id="client-photos-title"
                        className="text-lg font-semibold"
                    >
                        Fotos antes y después
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Solo para el registro interno del salón. No se publican
                        desde aquí.
                    </p>
                </div>
                <p
                    aria-label={`${photos.length} de ${max} fotos`}
                    className="shrink-0 pt-1 text-sm text-muted-foreground tabular-nums"
                >
                    {photos.length} de {max}
                </p>
            </div>

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

            {photos.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-6 text-center text-muted-foreground">
                    <Images className="size-6" aria-hidden />
                    <p>
                        Aún no hay fotos. Agrega la primera para recordar cómo
                        llegó y cómo quedó el trabajo.
                    </p>
                </div>
            ) : (
                <PhotoGrid clientId={clientId} photos={photos} />
            )}
        </section>
    );
}
