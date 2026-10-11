import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { PaymentMethodChips } from '@/components/accounting/payment-method-chips';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { store } from '@/routes/clients/photos';
import type { Option } from '@/types';
import { PhotoPicker } from './photo-picker';
import { PhotoUploadSubmit } from './photo-upload-submit';
import type { ClientPhotoKind } from './types';

const KINDS: Option<ClientPhotoKind>[] = [
    { value: 'before', label: 'Antes' },
    { value: 'after', label: 'Después' },
];

type Fields = { photo: File | null; kind: ClientPhotoKind; consent: boolean };

type Props = { clientId: number; clientName: string; onSaved: () => void };

/**
 * Adds one picture to the file. The person's permission is declared again
 * for every upload: the box always starts unchecked.
 */
export function PhotoUpload({ clientId, clientName, onSaved }: Props) {
    const form = useForm<Fields>({
        photo: null,
        kind: 'before',
        consent: false,
    });
    // The upload never arrived: the picture is still picked to retry.
    const [hasNetworkProblem, setHasNetworkProblem] = useState(false);
    const { data, setData, errors } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(store(clientId), {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setHasNetworkProblem(false),
            onNetworkError: () => setHasNetworkProblem(true),
            onSuccess: onSaved,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-4">
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
                        aria-describedby={
                            errors.consent
                                ? 'client-photo-consent-error'
                                : undefined
                        }
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

            <PhotoUploadSubmit
                hasPhoto={data.photo !== null}
                hasConsent={data.consent}
                processing={form.processing}
                percentage={form.progress?.percentage ?? null}
                hasNetworkProblem={hasNetworkProblem}
            />
        </form>
    );
}
