import { Trash2 } from 'lucide-react';
import { ReceiptViewer } from '@/components/expenses/receipt-viewer';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import { formatLongDay, groupPhotosByDay, photoCaption } from '@/lib/clients';
import { destroy } from '@/routes/clients/photos';
import type { ClientPhoto } from './types';

type Props = { clientId: number; photos: ClientPhoto[] };

type TileProps = { clientId: number; photo: ClientPhoto };

/** A thumbnail that opens the picture; removing it is asked from there. */
function PhotoTile({ clientId, photo }: TileProps) {
    const caption = photoCaption(photo);

    return (
        <li>
            <ReceiptViewer
                url={photo.url}
                caption={caption}
                noun="foto"
                alt={caption}
                className="relative block aspect-square size-auto w-full rounded-lg bg-muted"
                footer={
                    <div className="flex justify-end">
                        <ConfirmRemoveDialog
                            triggerLabel="Eliminar"
                            trigger={
                                <Button
                                    variant="outline"
                                    aria-label={`Eliminar foto: ${caption}`}
                                    className="text-destructive-foreground max-sm:w-full"
                                >
                                    <Trash2 aria-hidden /> Eliminar
                                </Button>
                            }
                            title="¿Eliminar esta foto?"
                            description={`${caption}. Se borra del registro del salón y no se puede recuperar desde la ficha.`}
                            confirmLabel="Sí, eliminar"
                            themed
                            form={destroy.form({
                                client: clientId,
                                photo: photo.id,
                            })}
                        />
                    </div>
                }
            >
                <span className="absolute bottom-2 left-2 rounded-full bg-card px-2.5 py-0.5 text-xs font-medium text-card-foreground shadow-card">
                    {photo.kindLabel}
                </span>
            </ReceiptViewer>
        </li>
    );
}

/** The pictures of the file, day by day, newest first. */
export function PhotoGrid({ clientId, photos }: Props) {
    return (
        <div className="flex flex-col gap-4">
            {groupPhotosByDay(photos).map((day) => (
                <section
                    key={day.date}
                    aria-label={`Fotos del ${formatLongDay(day.date)}`}
                    className="flex flex-col gap-2"
                >
                    <h3 className="text-sm font-medium text-muted-foreground tabular-nums">
                        {formatLongDay(day.date)}
                    </h3>
                    <ul className="grid grid-cols-3 gap-2 sm:gap-3 xl:grid-cols-4">
                        {day.photos.map((photo) => (
                            <PhotoTile
                                key={photo.id}
                                clientId={clientId}
                                photo={photo}
                            />
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}
