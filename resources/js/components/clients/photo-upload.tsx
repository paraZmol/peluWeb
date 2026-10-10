import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PaymentMethodChips } from '@/components/accounting/payment-method-chips';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/clients/photos';
import type { Option } from '@/types';
import { PhotoPicker } from './photo-picker';
import type { ClientPhotoKind } from './types';

const KINDS: Option<ClientPhotoKind>[] = [
    { value: 'before', label: 'Antes' },
    { value: 'after', label: 'Después' },
];

type Fields = { photo: File | null; kind: ClientPhotoKind; consent: boolean };

type Props = { clientId: number; clientName: string };

/**
 * Adds one picture to the file. The person's permission is declared again
 * for every upload: the box always starts unchecked.
 */
export function PhotoUpload({ clientId, clientName }: Props) {
    const form = useForm<Fields>({
        photo: null,
        kind: 'before',
        consent: false,
    });
    const { data, setData, errors, processing } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(store(clientId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form
            onSubmit={submit}
            noValidate
            aria-label="Subir una foto"
            className="flex flex-col gap-4 rounded-lg border bg-surface-2 p-4"
        >
            <div className="grid gap-2">
                <PaymentMethodChips
                    legend="¿Antes o después del servicio?"
                    methods={KINDS}
                    value={data.kind}
                    onChange={(kind) =>
                        setData('kind', kind as ClientPhotoKind)
                    }
                />
                <InputError message={errors.kind} />
            </div>

            <div className="grid gap-2">
                <ul className="list-disc pl-5 text-sm text-muted-foreground">
                    <li>Encuadra el trabajo, no la cara.</li>
                    <li>No subas fotos de menores de edad.</li>
                </ul>
                <PhotoPicker
                    file={data.photo}
                    onPick={(photo) => setData('photo', photo)}
                    onClear={() => setData('photo', null)}
                    error={errors.photo}
                />
            </div>

            <div className="grid gap-1">
                <label
                    htmlFor="client-photo-consent"
                    className="flex min-h-11 cursor-pointer items-start gap-3 py-2 text-sm"
                >
                    <Checkbox
                        id="client-photo-consent"
                        checked={data.consent}
                        onCheckedChange={(checked) =>
                            setData('consent', checked === true)
                        }
                        aria-invalid={Boolean(errors.consent)}
                        aria-describedby="client-photo-consent-error"
                        className="mt-0.5 size-5"
                    />
                    <span>
                        {clientName} autorizó estas fotos para uso interno. Le
                        avisé que no se publican y que puede pedir que se
                        borren.
                    </span>
                </label>
                <InputError
                    id="client-photo-consent-error"
                    message={errors.consent}
                />
            </div>

            <Button
                type="submit"
                disabled={processing || !data.photo || !data.consent}
                className="h-12 sm:h-10 sm:self-start"
            >
                {processing && <Spinner />}
                Guardar foto
            </Button>
        </form>
    );
}
