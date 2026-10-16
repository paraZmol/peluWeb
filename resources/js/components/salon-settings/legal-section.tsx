import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/salon/legal';
import { SettingsSection } from './settings-section';
import type { SalonSettings } from './types';

const INDECOPI_TOOL_URL = 'https://www.gob.pe/indecopi';

/**
 * Who provides the service at the salon and where its own Libro de
 * Reclamaciones is (requirement P7). All optional.
 */
export function LegalSection({ salon }: { salon: SalonSettings }) {
    const { legal } = salon;

    return (
        <SettingsSection
            id="libro"
            title="Libro de Reclamaciones"
            description="Todo local abierto al público debe tener uno. Tu página muestra el enlace o, si no lo tienes en línea, que está disponible en tu local."
        >
            <p className="text-sm text-muted-foreground">
                Indecopi ofrece una herramienta gratuita para generar tu Libro
                de Reclamaciones virtual.{' '}
                <a
                    href={INDECOPI_TOOL_URL}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="font-medium text-brand-text underline underline-offset-4"
                >
                    Ir a Indecopi
                </a>
            </p>
            <Form
                {...update.form()}
                noValidate
                options={{ preserveScroll: true }}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="complaints_book_url">
                                Enlace a tu Libro de Reclamaciones
                            </Label>
                            <Input
                                id="complaints_book_url"
                                name="complaints_book_url"
                                type="url"
                                inputMode="url"
                                defaultValue={legal.complaintsBookUrl ?? ''}
                                placeholder="https://"
                            />
                            <InputError message={errors.complaints_book_url} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="legal_name">
                                    Nombre o razón social
                                </Label>
                                <Input
                                    id="legal_name"
                                    name="legal_name"
                                    defaultValue={legal.legalName ?? ''}
                                    maxLength={200}
                                />
                                <InputError message={errors.legal_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ruc">RUC</Label>
                                <Input
                                    id="ruc"
                                    name="ruc"
                                    inputMode="numeric"
                                    defaultValue={legal.ruc ?? ''}
                                    maxLength={11}
                                    className="tabular-nums"
                                />
                                <InputError message={errors.ruc} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="contact_email">
                                    Correo del salón
                                </Label>
                                <Input
                                    id="contact_email"
                                    name="contact_email"
                                    type="email"
                                    defaultValue={legal.contactEmail ?? ''}
                                />
                                <InputError message={errors.contact_email} />
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            El nombre o razón social y el RUC se muestran al pie
                            de tu página, para que tus clientes sepan quién
                            presta el servicio. El correo no se publica.
                        </p>

                        <Button
                            type="submit"
                            className="rounded-full sm:self-end"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Guardar
                        </Button>
                    </>
                )}
            </Form>
        </SettingsSection>
    );
}
