import { router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Trash2 } from 'lucide-react';
import { ImageUploadButton } from '@/components/form/image-upload-button';
import { Button } from '@/components/ui/button';
import { destroy, reorder, store } from '@/routes/salon/photos';
import { SettingsSection } from './settings-section';
import type { SalonSettings } from './types';

const MAX_PHOTOS = 30;

export function GallerySection({ salon }: { salon: SalonSettings }) {
    const ids = salon.photos.map((photo) => photo.id);

    const move = (index: number, offset: -1 | 1) => {
        const next = [...ids];
        [next[index], next[index + offset]] = [
            next[index + offset],
            next[index],
        ];
        router.put(reorder(), { ids: next }, { preserveScroll: true });
    };

    return (
        <SettingsSection
            id="galeria"
            title="Galería"
            description={`Tu local y tus mejores trabajos (hasta ${MAX_PHOTOS} fotos). La primera aparece primero.`}
        >
            {salon.photos.length > 0 ? (
                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    {salon.photos.map((photo, index) => (
                        <li key={photo.id} className="flex flex-col gap-2">
                            <img
                                src={photo.url}
                                alt={
                                    photo.caption ??
                                    `Foto ${index + 1} de la galería`
                                }
                                loading="lazy"
                                className="aspect-square w-full rounded-lg border object-cover"
                            />
                            <div className="flex justify-between gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Mover antes"
                                    disabled={index === 0}
                                    onClick={() => move(index, -1)}
                                >
                                    <ArrowLeft />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Eliminar foto"
                                    onClick={() =>
                                        router.delete(destroy(photo.id), {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    <Trash2 />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Mover después"
                                    disabled={index === salon.photos.length - 1}
                                    onClick={() => move(index, 1)}
                                >
                                    <ArrowRight />
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Aún no hay fotos. Las fotos de trabajos reales son lo que
                    más convence a un cliente nuevo.
                </p>
            )}
            {salon.photos.length < MAX_PHOTOS && (
                <ImageUploadButton
                    route={store()}
                    label="Agregar fotos"
                    multiple
                />
            )}
        </SettingsSection>
    );
}
