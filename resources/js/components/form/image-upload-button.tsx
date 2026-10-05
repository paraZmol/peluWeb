import { router } from '@inertiajs/react';
import { ImagePlus } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { RouteDefinition } from '@/wayfinder';

const MAX_BYTES = 5 * 1024 * 1024;

type Props = {
    route: RouteDefinition<'post'>;
    label: string;
    multiple?: boolean;
    variant?: 'default' | 'outline';
};

/**
 * Picks image(s) and uploads them one by one to the given route as the
 * `image` field. Size is checked first so people get instant feedback.
 */
export function ImageUploadButton({
    route,
    label,
    multiple = false,
    variant = 'outline',
}: Props) {
    const input = useRef<HTMLInputElement>(null);
    const [pending, setPending] = useState(0);
    const [error, setError] = useState<string>();

    const upload = (files: File[]) => {
        const tooBig = files.find((file) => file.size > MAX_BYTES);

        if (tooBig) {
            setError(
                `"${tooBig.name}" pesa más de 5 MB. Elige una más liviana.`,
            );

            return;
        }

        setError(undefined);
        setPending(files.length);
        files.forEach((file) =>
            router.post(
                route,
                { image: file },
                {
                    forceFormData: true,
                    preserveScroll: true,
                    onError: (errors) =>
                        setError(errors.image ?? Object.values(errors)[0]),
                    onFinish: () => setPending((count) => count - 1),
                },
            ),
        );
    };

    return (
        <div className="flex flex-col gap-1">
            <Button
                type="button"
                variant={variant}
                disabled={pending > 0}
                onClick={() => input.current?.click()}
            >
                {pending > 0 ? <Spinner /> : <ImagePlus />}
                {label}
            </Button>
            <input
                ref={input}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple={multiple}
                className="sr-only"
                aria-label={label}
                onChange={(event) => {
                    const files = Array.from(event.target.files ?? []);
                    event.target.value = '';

                    if (files.length > 0) {
                        upload(files);
                    }
                }}
            />
            <InputError message={error} />
        </div>
    );
}
