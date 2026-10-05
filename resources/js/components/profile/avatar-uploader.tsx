import { router, usePage } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/hooks/use-initials';
import { destroy, store } from '@/routes/avatar';

const MAX_BYTES = 5 * 1024 * 1024;

export function AvatarUploader() {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const input = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string>();

    const upload = (file: File) => {
        if (file.size > MAX_BYTES) {
            setError('La foto pesa más de 5 MB. Elige una más liviana.');

            return;
        }

        setError(undefined);
        router.post(
            store(),
            { avatar: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onFinish: () => setUploading(false),
                onError: (errors) => setError(errors.avatar),
            },
        );
    };

    return (
        <div className="flex items-center gap-4">
            <Avatar className="size-20">
                <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                <AvatarFallback className="text-lg">
                    {getInitials(auth.user.name)}
                </AvatarFallback>
            </Avatar>

            <div className="flex flex-col gap-2">
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={uploading}
                        onClick={() => input.current?.click()}
                    >
                        {uploading ? <Spinner /> : <Camera />}
                        Cambiar foto
                    </Button>
                    {auth.user.avatar && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={uploading}
                            onClick={() =>
                                router.delete(destroy(), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            Quitar
                        </Button>
                    )}
                </div>
                <p className="text-xs text-muted-foreground">
                    JPG, PNG o WebP de hasta 5 MB.
                </p>
                <InputError message={error} />
            </div>

            <input
                ref={input}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="sr-only"
                aria-label="Elegir foto de perfil"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = '';

                    if (file) {
                        upload(file);
                    }
                }}
            />
        </div>
    );
}
