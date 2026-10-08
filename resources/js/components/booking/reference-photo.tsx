import { router } from '@inertiajs/react';
import { ImageUploadButton } from '@/components/form/image-upload-button';
import { Button } from '@/components/ui/button';
import { destroy, store } from '@/routes/bookings/photo';

type Props = {
    code: string;
    url: string | null;
    /** Only while the visit is still to come. */
    canChange: boolean;
};

/** "Mi estilo": a picture of what the client wants, for whoever attends. */
export function ReferencePhoto({ code, url, canChange }: Props) {
    if (!url && !canChange) {
        return null;
    }

    return (
        <section
            aria-labelledby="reference-photo-title"
            className="flex flex-col gap-3 rounded-xl border bg-card p-4"
        >
            <div>
                <h2
                    id="reference-photo-title"
                    className="font-sans text-base font-semibold"
                >
                    Mi estilo
                </h2>
                <p className="text-sm text-muted-foreground">
                    {url
                        ? 'Quien te atienda verá esta foto antes de tu cita.'
                        : 'Sube una foto de lo que quieres y quien te atienda la verá antes de tu cita.'}
                </p>
            </div>

            {url && (
                <img
                    src={url}
                    alt="Foto de referencia de tu estilo"
                    className="max-h-80 w-full rounded-lg bg-muted object-contain"
                />
            )}

            {canChange && (
                <div className="flex flex-wrap items-start gap-2 [&_button]:h-11 sm:[&_button]:h-9">
                    <ImageUploadButton
                        route={store(code)}
                        label={url ? 'Cambiar foto' : 'Subir foto'}
                    />
                    {url && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() =>
                                router.delete(destroy(code), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Quitar foto
                        </Button>
                    )}
                </div>
            )}
        </section>
    );
}
