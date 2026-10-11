import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DialogFooter } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { grant } from '@/routes/my-data';
import type { ConsentKind } from './types';

/** The same words the server answers with when the box is missing. */
const ACCEPT_REQUIRED = 'Marca la casilla para autorizar.';

type Props = {
    clientId: number;
    kind: ConsentKind;
    acceptLabel: string;
    /** Authorized, or "Ahora no". */
    onDone: () => void;
};

/** The box and the two ways out. Without the box nothing is sent. */
export function AuthorizeForm({ clientId, kind, acceptLabel, onDone }: Props) {
    const form = useForm({ accept: false });
    // The request never arrived: the box stays as it was to retry.
    const [hasNetworkProblem, setHasNetworkProblem] = useState(false);
    const boxId = `my-data-accept-${clientId}-${kind}`;
    const error = form.errors.accept;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!form.data.accept) {
            form.setError('accept', ACCEPT_REQUIRED);

            return;
        }

        form.submit(grant([clientId, kind]), {
            preserveScroll: true,
            onStart: () => setHasNetworkProblem(false),
            onNetworkError: () => setHasNetworkProblem(true),
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-4">
            <div className="grid gap-1">
                <label
                    htmlFor={boxId}
                    className="flex min-h-11 cursor-pointer items-start gap-3 py-2 text-sm"
                >
                    <Checkbox
                        id={boxId}
                        checked={form.data.accept}
                        onCheckedChange={(checked) => {
                            form.setData('accept', checked === true);
                            form.clearErrors('accept');
                        }}
                        aria-invalid={Boolean(error)}
                        aria-describedby={error ? `${boxId}-error` : undefined}
                        className="mt-0.5 size-5"
                    />
                    <span>{acceptLabel}</span>
                </label>
                <InputError
                    id={`${boxId}-error`}
                    role="alert"
                    message={error}
                    className="pl-8"
                />
            </div>

            {hasNetworkProblem && (
                <p role="alert" className="text-sm text-destructive-foreground">
                    No se pudo enviar. Revisa tu conexión y vuelve a intentarlo.
                </p>
            )}

            <DialogFooter className="gap-2 [&_button]:h-11 sm:[&_button]:h-9">
                <Button type="button" variant="outline" onClick={onDone}>
                    Ahora no
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Autorizar
                </Button>
            </DialogFooter>
        </form>
    );
}
