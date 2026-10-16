import { useState } from 'react';
import { ChoiceCards } from '@/components/form/choice-cards';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Option } from '@/types';
import { FormField } from './form-field';

type Props = {
    documents: Option[];
    errors: Partial<Record<string, string>>;
};

/** "Quien reclama": who files the sheet and how to reach them. */
export function ConsumerFields({ documents, errors }: Props) {
    const [isMinor, setIsMinor] = useState(false);

    return (
        <fieldset className="grid gap-5">
            <legend className="mb-1 text-base font-semibold">Tus datos</legend>

            <FormField
                id="consumer_name"
                label="Nombre completo"
                error={errors.consumer_name}
            >
                <Input
                    id="consumer_name"
                    name="consumer_name"
                    autoComplete="name"
                    maxLength={150}
                    required
                />
            </FormField>

            <FormField
                id="consumer_address"
                label="Domicilio"
                error={errors.consumer_address}
            >
                <Input
                    id="consumer_address"
                    name="consumer_address"
                    autoComplete="street-address"
                    maxLength={255}
                    required
                />
            </FormField>

            <div className="grid gap-3">
                <span className="text-sm font-medium">Documento</span>
                <ChoiceCards
                    name="document_type"
                    options={documents}
                    defaultValue={documents[0]?.value}
                />
            </div>
            <FormField
                id="document_number"
                label="Número de documento"
                error={errors.document_number ?? errors.document_type}
            >
                <Input
                    id="document_number"
                    name="document_number"
                    inputMode="text"
                    maxLength={12}
                    required
                />
            </FormField>

            <div className="grid gap-5 sm:grid-cols-2">
                <FormField id="phone" label="Teléfono" error={errors.phone}>
                    <Input
                        id="phone"
                        name="phone"
                        type="tel"
                        inputMode="tel"
                        autoComplete="tel"
                        required
                    />
                </FormField>
                <FormField id="email" label="Correo" error={errors.email}>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        autoComplete="email"
                        required
                    />
                </FormField>
            </div>

            <div className="flex items-center gap-3">
                <Checkbox
                    id="is_minor"
                    name="is_minor"
                    value="1"
                    checked={isMinor}
                    onCheckedChange={(state) => setIsMinor(state === true)}
                />
                <Label htmlFor="is_minor">Soy menor de edad</Label>
            </div>
            {isMinor && (
                <FormField
                    id="guardian_name"
                    label="Nombre de tu madre, padre o tutor"
                    error={errors.guardian_name}
                >
                    <Input
                        id="guardian_name"
                        name="guardian_name"
                        maxLength={150}
                        required
                    />
                </FormField>
            )}
        </fieldset>
    );
}
