import { Form, Head } from '@inertiajs/react';
import { ChoiceCards } from '@/components/form/choice-cards';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/salons';
import type { Option } from '@/types';

type Props = {
    businessTypes: Option[];
    clienteles: Option[];
};

export default function CreateSalon({ businessTypes, clienteles }: Props) {
    return (
        <>
            <Head title="Crea tu salón" />

            <div className="mx-auto w-full max-w-xl px-4 py-8">
                <h1 className="text-3xl font-semibold">Crea tu salón</h1>
                <p className="mt-2 text-muted-foreground">
                    Solo lo básico. Fotos, servicios y horarios los agregas
                    después. Tienes 30 días de prueba gratis.
                </p>

                <Form {...store.form()} className="mt-8 flex flex-col gap-8">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre del salón</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoFocus
                                    maxLength={120}
                                    placeholder="Ej. Barbería Don Lucho"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <fieldset className="grid gap-3">
                                <legend className="mb-3 text-sm font-medium">
                                    ¿Qué tipo de negocio es?
                                </legend>
                                <ChoiceCards
                                    name="business_type"
                                    options={businessTypes}
                                />
                                <InputError message={errors.business_type} />
                            </fieldset>

                            <fieldset className="grid gap-3">
                                <legend className="mb-3 text-sm font-medium">
                                    ¿A quién atiendes?
                                </legend>
                                <ChoiceCards
                                    name="clientele"
                                    options={clienteles}
                                    defaultValue="everyone"
                                    columns={3}
                                />
                                <InputError message={errors.clientele} />
                            </fieldset>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">
                                    WhatsApp del salón{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (opcional)
                                    </span>
                                </Label>
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="tel"
                                    placeholder="+51 999 888 777"
                                    aria-describedby="phone-hint"
                                />
                                <p
                                    id="phone-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    Lo usarán tus clientes para confirmar sus
                                    reservas.
                                </p>
                                <InputError message={errors.phone} />
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                className="w-full rounded-full"
                                disabled={processing}
                                data-test="create-salon-button"
                            >
                                {processing && <Spinner />}
                                Crear mi salón
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
