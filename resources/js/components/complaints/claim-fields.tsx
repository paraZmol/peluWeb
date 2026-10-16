import { ChoiceCards } from '@/components/form/choice-cards';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { Option } from '@/types';
import { FormField } from './form-field';
import type { ComplaintKindOption } from './types';

type Props = {
    kinds: ComplaintKindOption[];
    itemTypes: Option[];
    errors: Partial<Record<string, string>>;
};

/** "Sobre qué" and "Detalle": the kind of sheet, what about, and the ask. */
export function ClaimFields({ kinds, itemTypes, errors }: Props) {
    return (
        <fieldset className="grid gap-5">
            <legend className="mb-1 text-base font-semibold">Tu reclamo</legend>

            <div className="grid gap-3">
                <span className="text-sm font-medium">Tipo</span>
                <ChoiceCards
                    name="kind"
                    options={kinds}
                    describedBy="kind-help"
                />
                <dl id="kind-help" className="grid gap-1 text-sm">
                    {kinds.map((kind) => (
                        <div key={kind.value}>
                            <dt className="inline font-semibold">
                                {kind.label}:
                            </dt>{' '}
                            <dd className="inline text-muted-foreground">
                                {kind.help}
                            </dd>
                        </div>
                    ))}
                </dl>
                <InputError message={errors.kind} />
            </div>

            <div className="grid gap-3">
                <span className="text-sm font-medium">¿Sobre qué es?</span>
                <ChoiceCards
                    name="item_type"
                    options={itemTypes}
                    defaultValue={itemTypes[0]?.value}
                />
                <InputError message={errors.item_type} />
            </div>

            <FormField
                id="item_description"
                label="Descripción del producto o servicio"
                hint="Ej. Suscripción al plan mensual, reservas en línea."
                error={errors.item_description}
            >
                <Input
                    id="item_description"
                    name="item_description"
                    maxLength={255}
                    aria-describedby="item_description-hint"
                    required
                />
            </FormField>

            <FormField
                id="claimed_amount"
                label="Monto reclamado en soles (opcional)"
                error={errors.claimed_amount}
            >
                <Input
                    id="claimed_amount"
                    name="claimed_amount"
                    inputMode="decimal"
                    placeholder="0.00"
                    className="tabular-nums sm:max-w-48"
                />
            </FormField>

            <FormField
                id="detail"
                label="Detalle: ¿qué pasó?"
                error={errors.detail}
            >
                <Textarea
                    id="detail"
                    name="detail"
                    rows={5}
                    maxLength={3000}
                    required
                />
            </FormField>

            <FormField
                id="request"
                label="Pedido: ¿qué solicitas?"
                error={errors.request}
            >
                <Textarea
                    id="request"
                    name="request"
                    rows={3}
                    maxLength={2000}
                    required
                />
            </FormField>
        </fieldset>
    );
}
