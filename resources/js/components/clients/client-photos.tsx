import { Images } from 'lucide-react';
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

            {isFull ? (
                <p className="rounded-lg bg-warning/10 p-3 text-sm">
                    Esta ficha ya tiene sus {max} fotos. Elimina alguna para
                    subir otra.
                </p>
            ) : (
                <PhotoUpload clientId={clientId} clientName={clientName} />
            )}

            {photos.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-6 text-center text-muted-foreground">
                    <Images className="size-6" aria-hidden />
                    <p>
                        Aún no hay fotos. Sube la primera para recordar cómo
                        llegó y cómo quedó el trabajo.
                    </p>
                </div>
            ) : (
                <PhotoGrid clientId={clientId} photos={photos} />
            )}
        </section>
    );
}
