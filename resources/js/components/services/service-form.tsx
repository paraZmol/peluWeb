import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatMinutes } from '@/lib/format';
import { store, update } from '@/routes/services';
import type { Option } from '@/types';
import type { SalonService } from './types';
import { VariantsEditor } from './variants-editor';
import type { VariantDraft } from './variants-editor';

const MAX_VARIANTS = 6;

type Props = {
    service?: SalonService;
    categories: Option[];
    targets: Option[];
    onSaved: () => void;
};

export function ServiceForm({ service, categories, targets, onSaved }: Props) {
    const form = useForm({
        name: service?.name ?? '',
        category: service?.category ?? categories[0]?.value ?? '',
        target: service?.target ?? 'unisex',
        description: service?.description ?? '',
        price: service?.price ?? '',
        active_minutes: service?.activeMinutes ?? 30,
        idle_minutes: service?.idleMinutes ?? 0,
        finish_minutes: service?.finishMinutes ?? 0,
        is_addon: service?.isAddon ?? false,
        requires_evaluation: service?.requiresEvaluation ?? false,
        requires_patch_test: service?.requiresPatchTest ?? false,
        variants: (service?.variants ?? []).map((v): VariantDraft => ({
            id: v.id,
            label: v.label,
            price: v.price,
            extra_minutes: v.extraMinutes,
        })),
    });
    const { data, setData, errors, processing } = form;
    const total =
        Number(data.active_minutes) +
        Number(data.idle_minutes) +
        Number(data.finish_minutes);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onSaved };
        form.submit(service ? update(service.id) : store(), options);
    };

    const minutesField = (
        key: 'active_minutes' | 'idle_minutes' | 'finish_minutes',
        label: string,
        hint: string,
    ) => (
        <div className="grid gap-1">
            <Label htmlFor={key}>{label}</Label>
            <Input
                id={key}
                type="number"
                inputMode="numeric"
                min={0}
                step={5}
                value={data[key]}
                onChange={(e) => setData(key, Number(e.target.value))}
            />
            <span className="text-xs text-muted-foreground">{hint}</span>
            <InputError message={errors[key]} />
        </div>
    );

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-5">
            <div className="grid gap-2">
                <Label htmlFor="service-name">Nombre</Label>
                <Input
                    id="service-name"
                    value={data.name}
                    maxLength={120}
                    onChange={(e) => setData('name', e.target.value)}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label>Categoría</Label>
                    <Select
                        value={data.category}
                        onValueChange={(value) => setData('category', value)}
                    >
                        <SelectTrigger aria-label="Categoría">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {categories.map((c) => (
                                <SelectItem key={c.value} value={c.value}>
                                    {c.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-2">
                    <Label>Para</Label>
                    <Select
                        value={data.target}
                        onValueChange={(value) => setData('target', value)}
                    >
                        <SelectTrigger aria-label="Para quién es">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {targets.map((t) => (
                                <SelectItem key={t.value} value={t.value}>
                                    {t.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="service-description">
                    Descripción (opcional)
                </Label>
                <Textarea
                    id="service-description"
                    className="min-h-16"
                    value={data.description}
                    maxLength={255}
                    onChange={(e) => setData('description', e.target.value)}
                />
                <InputError message={errors.description} />
            </div>

            <div className="grid gap-2 sm:max-w-40">
                <Label htmlFor="service-price">Precio (S/)</Label>
                <Input
                    id="service-price"
                    inputMode="decimal"
                    placeholder="35.00"
                    value={data.price}
                    onChange={(e) => setData('price', e.target.value)}
                />
                <InputError message={errors.price} />
            </div>

            <fieldset className="grid gap-3">
                <legend className="mb-1 text-sm font-medium">
                    Tiempo · total {formatMinutes(total)}
                </legend>
                <div className="grid gap-4 sm:grid-cols-3">
                    {minutesField(
                        'active_minutes',
                        'Trabajo',
                        'Tiempo atendiendo',
                    )}
                    {minutesField(
                        'idle_minutes',
                        'Espera',
                        'Ej. reposo del tinte: puedes atender a otra persona',
                    )}
                    {minutesField(
                        'finish_minutes',
                        'Final',
                        'Enjuague, secado, peinado',
                    )}
                </div>
            </fieldset>

            <VariantsEditor
                value={data.variants}
                onChange={(variants) => setData('variants', variants)}
                max={MAX_VARIANTS}
            />
            <InputError
                message={
                    errors.variants ??
                    Object.entries(errors).find(([key]) =>
                        key.startsWith('variants.'),
                    )?.[1]
                }
            />

            <div className="flex flex-col gap-2">
                {(
                    [
                        [
                            'is_addon',
                            'Es un adicional (se agrega a otro servicio, no se reserva solo)',
                        ],
                        [
                            'requires_evaluation',
                            'El precio final se confirma en el salón',
                        ],
                        [
                            'requires_patch_test',
                            'Requiere prueba de alergia 48 h antes',
                        ],
                    ] as const
                ).map(([key, label]) => (
                    <label
                        key={key}
                        className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm"
                    >
                        <Checkbox
                            checked={data[key]}
                            onCheckedChange={(checked) =>
                                setData(key, checked === true)
                            }
                        />
                        {label}
                    </label>
                ))}
            </div>

            <Button
                type="submit"
                className="rounded-full"
                disabled={processing}
            >
                {processing && <Spinner />}
                {service ? 'Guardar cambios' : 'Crear servicio'}
            </Button>
        </form>
    );
}
