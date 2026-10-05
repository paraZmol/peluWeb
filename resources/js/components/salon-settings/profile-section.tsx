import { Form } from '@inertiajs/react';
import { ChoiceCards } from '@/components/form/choice-cards';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { update } from '@/routes/salon/profile';
import type { Option } from '@/types';
import { SettingsSection } from './settings-section';
import type { SalonSettings } from './types';

type Props = {
    salon: SalonSettings;
    businessTypes: Option[];
    clienteles: Option[];
};

export function ProfileSection({ salon, businessTypes, clienteles }: Props) {
    return (
        <SettingsSection
            id="datos"
            title="Datos del salón"
            description="Lo que ven tus clientes en tu página."
        >
            <Form
                {...update.form()}
                noValidate
                options={{ preserveScroll: true }}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre</Label>
                            <Input
                                id="name"
                                name="name"
                                defaultValue={salon.name}
                                maxLength={120}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <fieldset className="grid gap-3">
                            <legend className="mb-3 text-sm font-medium">
                                Tipo de negocio
                            </legend>
                            <ChoiceCards
                                name="business_type"
                                options={businessTypes}
                                defaultValue={salon.businessType}
                            />
                        </fieldset>

                        <fieldset className="grid gap-3">
                            <legend className="mb-3 text-sm font-medium">
                                ¿A quién atiendes?
                            </legend>
                            <ChoiceCards
                                name="clientele"
                                options={clienteles}
                                defaultValue={salon.clientele}
                                columns={3}
                            />
                        </fieldset>

                        <div className="grid gap-2">
                            <Label htmlFor="description">Descripción</Label>
                            <Textarea
                                id="description"
                                name="description"
                                defaultValue={salon.description ?? ''}
                                maxLength={600}
                                placeholder="Ej. Cortes clásicos y modernos en el corazón de Miraflores desde 1998."
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="phone">WhatsApp</Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    inputMode="tel"
                                    defaultValue={salon.phone ?? ''}
                                    placeholder="+51 999 888 777"
                                />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="instagram">Instagram</Label>
                                <Input
                                    id="instagram"
                                    name="instagram"
                                    defaultValue={
                                        salon.instagram
                                            ? `@${salon.instagram}`
                                            : ''
                                    }
                                    placeholder="@tusalon"
                                />
                                <InputError message={errors.instagram} />
                            </div>
                        </div>

                        <Button
                            type="submit"
                            className="rounded-full sm:self-end"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Guardar datos
                        </Button>
                    </>
                )}
            </Form>
        </SettingsSection>
    );
}
