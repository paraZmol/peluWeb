import { Images } from 'lucide-react';
import { cn } from '@/lib/utils';
import { PhotoAddDialog } from './photo-add-dialog';
import { PhotoGrid } from './photo-grid';
import type { ClientPhoto } from './types';
import { WarningNote } from './warning-note';

type Props = {
    clientId: number;
    clientName: string;
    photos: ClientPhoto[];
    /** How many pictures a file keeps at most. */
    max: number;
    /**
     * The person took their pictures back: no more are added until they
     * allow it from their account. Null while uploads are open.
     */
    withdrawalNotice: string | null;
    className?: string;
};

/** Before and after pictures of the work, for the salon's own record. */
export function ClientPhotos({
    clientId,
    clientName,
    photos,
    max,
    withdrawalNotice,
    className,
}: Props) {
    const isBlocked = withdrawalNotice !== null;

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
                        Solo para el registro interno del salón. No las
                        publiques ni las envíes: para eso hace falta otra
                        autorización escrita de la persona.
                    </p>
                </div>
                <p
                    aria-label={`${photos.length} de ${max} fotos`}
                    className="shrink-0 pt-1 text-sm text-muted-foreground tabular-nums"
                >
                    {photos.length} de {max}
                </p>
            </div>

            {isBlocked ? (
                <WarningNote>{withdrawalNotice}</WarningNote>
            ) : (
                <PhotoAddDialog
                    clientId={clientId}
                    clientName={clientName}
                    isFull={photos.length >= max}
                    max={max}
                />
            )}

            {photos.length > 0 && (
                <PhotoGrid clientId={clientId} photos={photos} />
            )}
            {photos.length === 0 && !isBlocked && (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-6 text-center text-muted-foreground">
                    <Images className="size-6" aria-hidden />
                    <p>
                        Aún no hay fotos. Agrega la primera para recordar cómo
                        llegó y cómo quedó el trabajo.
                    </p>
                </div>
            )}
        </section>
    );
}
