import { ReceiptViewer } from '@/components/expenses/receipt-viewer';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import { formatLongDay, photoCaption } from '@/lib/clients';
import {
    photosBlockedNotice,
    photosCountLabel,
    photosEraseWarning,
} from '@/lib/my-data';
import { withdraw } from '@/routes/my-data';
import { AuthorizeDialog } from './authorize-dialog';
import { Fold } from './fold';
import type { MyDataFile, MyDataPhoto } from './types';

/** The pictures, small; each opens big enough to look at. */
function Thumbnails({ photos }: { photos: MyDataPhoto[] }) {
    return (
        <ul className="grid grid-cols-3 gap-2 sm:grid-cols-4">
            {photos.map((photo) => {
                const caption = photoCaption(photo);

                return (
                    <li key={photo.id}>
                        <ReceiptViewer
                            url={photo.url}
                            caption={caption}
                            noun="foto"
                            alt={caption}
                            className="block aspect-square size-auto w-full rounded-lg bg-muted"
                        />
                    </li>
                );
            })}
        </ul>
    );
}

/**
 * "Fotos de tus servicios": the before and after pictures this salon keeps,
 * and the switch to take them back or to allow them again.
 */
export function PhotosSection({ file }: { file: MyDataFile }) {
    const { photos, salon, clientId } = file;
    const titleId = `my-data-photos-${clientId}`;
    const count = photos.items.length;
    const canWithdraw = count > 0 || photos.isAuthorized;
    const withdrawLabel =
        count > 0 ? 'Retirar y borrar fotos' : 'Retirar autorización';

    return (
        <section
            aria-labelledby={titleId}
            className="flex min-w-0 flex-col gap-3"
        >
            <h3 id={titleId} className="text-base font-semibold">
                Fotos de tus servicios
            </h3>

            {count === 0 && photos.isAuthorized && (
                <p className="text-sm">
                    {photos.since === null
                        ? 'Autorizaste'
                        : `Autorizaste el ${formatLongDay(photos.since)}`}
                    . {salon.name} todavía no guarda fotos tuyas.
                </p>
            )}

            {count > 0 && (
                <>
                    <p className="text-sm tabular-nums">
                        {photosCountLabel(count)}
                    </p>
                    <Fold summary="Ver las fotos">
                        <Thumbnails photos={photos.items} />
                    </Fold>
                </>
            )}

            {canWithdraw && (
                <ConfirmRemoveDialog
                    triggerLabel={withdrawLabel}
                    trigger={
                        <Button
                            variant="outline"
                            className="h-11 text-destructive-foreground sm:h-9 sm:self-start"
                        >
                            {withdrawLabel}
                        </Button>
                    }
                    title="¿Retirar la autorización para fotos?"
                    description={photosEraseWarning(count, salon.name)}
                    confirmLabel={withdrawLabel}
                    form={withdraw.form([clientId, 'photos'])}
                />
            )}

            {count === 0 && photos.isBlocked && (
                <>
                    <p className="text-sm">
                        {photosBlockedNotice(photos.withdrawnOn, salon.name)}
                    </p>
                    {!salon.isClosed && (
                        <AuthorizeDialog
                            clientId={clientId}
                            kind="photos"
                            triggerLabel="Volver a autorizar fotos"
                            title={`Autorizar a ${salon.name} a guardar fotos de mis servicios`}
                            text={photos.text}
                            acceptLabel={`Soy mayor de 18 años, leí lo anterior y autorizo a ${salon.name} a guardar estas fotos.`}
                        />
                    )}
                </>
            )}

            {count === 0 && !photos.isBlocked && !photos.isAuthorized && (
                <p className="text-sm text-muted-foreground">
                    {salon.name} no guarda fotos tuyas.
                </p>
            )}
        </section>
    );
}
