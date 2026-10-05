import { Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type VariantDraft = {
    id?: number;
    label: string;
    price: string;
    extra_minutes: number;
};

type Props = {
    value: VariantDraft[];
    onChange: (variants: VariantDraft[]) => void;
    max: number;
};

/**
 * Options with their own price, e.g. by hair length. Optional: most
 * services have none.
 */
export function VariantsEditor({ value, onChange, max }: Props) {
    const update = (index: number, patch: Partial<VariantDraft>) =>
        onChange(
            value.map((variant, i) =>
                i === index ? { ...variant, ...patch } : variant,
            ),
        );

    return (
        <fieldset className="flex flex-col gap-3">
            <legend className="mb-1 text-sm font-medium">
                Opciones con otro precio (opcional)
            </legend>
            <p className="text-sm text-muted-foreground">
                Por ejemplo, cabello corto, medio y largo.
            </p>
            {value.map((variant, index) => (
                <div
                    key={variant.id ?? `new-${index}`}
                    className="grid grid-cols-[1fr_6rem_6rem_auto] items-center gap-2"
                >
                    <Input
                        aria-label="Nombre de la opción"
                        value={variant.label}
                        placeholder="Cabello largo"
                        onChange={(e) =>
                            update(index, { label: e.target.value })
                        }
                    />
                    <Input
                        aria-label="Precio en soles"
                        inputMode="decimal"
                        value={variant.price}
                        placeholder="S/"
                        onChange={(e) =>
                            update(index, { price: e.target.value })
                        }
                    />
                    <Input
                        aria-label="Minutos extra"
                        type="number"
                        inputMode="numeric"
                        value={variant.extra_minutes}
                        onChange={(e) =>
                            update(index, {
                                extra_minutes: Number(e.target.value),
                            })
                        }
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Quitar opción"
                        onClick={() =>
                            onChange(value.filter((_, i) => i !== index))
                        }
                    >
                        <X />
                    </Button>
                </div>
            ))}
            {value.length > 0 && (
                <p className="text-xs text-muted-foreground">
                    Columnas: nombre · precio · minutos extra
                </p>
            )}
            {value.length < max && (
                <Button
                    type="button"
                    variant="outline"
                    className="self-start"
                    onClick={() =>
                        onChange([
                            ...value,
                            { label: '', price: '', extra_minutes: 0 },
                        ])
                    }
                >
                    <Plus /> Agregar opción
                </Button>
            )}
        </fieldset>
    );
}
