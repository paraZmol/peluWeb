import { Camera, ImagePlus, X } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { IMAGE_TYPES, MAX_IMAGE_BYTES, shrinkPhoto } from '@/lib/image';

type Props = {
    /** The photo just picked, not uploaded yet. */
    file: File | null;
    onPick: (file: File) => void;
    onClear: () => void;
    /** What the server said about the photo. */
    error?: string;
};

/**
 * Picks the picture of the work, with its preview. On a phone it can be
 * taken on the spot: the camera input only shows where the pointer is a
 * finger (same as the receipt of an expense).
 */
export function PhotoPicker({ file, onPick, onClear, error }: Props) {
    const camera = useRef<HTMLInputElement>(null);
    const gallery = useRef<HTMLInputElement>(null);
    const [isReading, setIsReading] = useState(false);
    const [problem, setProblem] = useState<string>();
    const preview = useMemo(
        () => (file ? URL.createObjectURL(file) : null),
        [file],
    );

    useEffect(
        () => () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        },
        [preview],
    );

    const read = async (event: ChangeEvent<HTMLInputElement>) => {
        const picked = event.target.files?.[0];
        event.target.value = '';

        if (!picked) {
            return;
        }

        setIsReading(true);
        const photo = await shrinkPhoto(picked);
        setIsReading(false);

        if (photo.size > MAX_IMAGE_BYTES) {
            setProblem('La foto pesa más de 5 MB. Elige una más liviana.');

            return;
        }

        setProblem(undefined);
        onPick(photo);
    };

    return (
        <div className="grid gap-2">
            {preview && (
                <img
                    src={preview}
                    alt="Vista previa de la foto"
                    className="max-h-56 w-full rounded-md border bg-muted object-contain"
                />
            )}
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={isReading}
                    className="hidden pointer-coarse:inline-flex"
                    onClick={() => camera.current?.click()}
                >
                    {isReading ? <Spinner /> : <Camera aria-hidden />}
                    {preview ? 'Tomar otra' : 'Tomar foto'}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={isReading}
                    onClick={() => gallery.current?.click()}
                >
                    <ImagePlus aria-hidden />
                    {preview ? 'Cambiar foto' : 'Elegir foto'}
                </Button>
                {preview && (
                    <Button type="button" variant="ghost" onClick={onClear}>
                        <X aria-hidden /> Quitar
                    </Button>
                )}
            </div>
            <input
                ref={camera}
                type="file"
                accept="image/*"
                capture="environment"
                tabIndex={-1}
                className="sr-only"
                aria-label="Tomar foto del trabajo"
                onChange={read}
            />
            <input
                ref={gallery}
                type="file"
                accept={IMAGE_TYPES}
                tabIndex={-1}
                className="sr-only"
                aria-label="Foto del trabajo"
                onChange={read}
            />
            <InputError role="alert" message={problem ?? error} />
        </div>
    );
}
