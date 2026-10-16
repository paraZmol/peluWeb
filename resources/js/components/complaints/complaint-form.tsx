import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/complaints';
import { ClaimFields } from './claim-fields';
import { ConsumerFields } from './consumer-fields';
import type { ComplaintFormOptions } from './types';

const TOO_MANY_REQUESTS = 429;

/**
 * The sheet itself. Native validation is off: the server's messages are
 * the ones in Spanish and in context.
 */
export function ComplaintForm({
    kinds,
    documents,
    itemTypes,
}: ComplaintFormOptions) {
    const [isRateLimited, setIsRateLimited] = useState(false);

    return (
        <Form
            {...store.form()}
            noValidate
            onHttpException={(response) => {
                if (response.status !== TOO_MANY_REQUESTS) {
                    return;
                }

                setIsRateLimited(true);

                return false;
            }}
            className="flex flex-col gap-8"
        >
            {/* A trap for bots: hidden from people and from screen readers. */}
            <input
                type="text"
                name="website"
                tabIndex={-1}
                autoComplete="off"
                aria-hidden
                className="hidden"
            />
            {({ processing, errors }) => (
                <>
                    <ConsumerFields documents={documents} errors={errors} />
                    <ClaimFields
                        kinds={kinds}
                        itemTypes={itemTypes}
                        errors={errors}
                    />

                    <div className="grid gap-2">
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="consent"
                                name="consent"
                                value="1"
                                className="mt-0.5"
                            />
                            <Label htmlFor="consent" className="leading-snug">
                                Declaro que los datos son verdaderos y acepto
                                que se usen para atender este reclamo.
                            </Label>
                        </div>
                        <InputError message={errors.consent} />
                        <p className="text-sm text-muted-foreground">
                            Usamos estos datos solo para atender tu reclamo y
                            los guardamos el tiempo que exige la ley. Tu
                            documento y tu domicilio no se usan para nada más.
                        </p>
                    </div>

                    {isRateLimited && (
                        <p
                            role="alert"
                            className="rounded-lg bg-muted p-3 text-sm"
                        >
                            Se enviaron muchas hojas desde esta conexión. Espera
                            una hora y vuelve a intentarlo.
                        </p>
                    )}

                    <Button
                        type="submit"
                        className="h-12 rounded-full sm:self-end sm:px-8"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Enviar hoja de reclamación
                    </Button>
                </>
            )}
        </Form>
    );
}
