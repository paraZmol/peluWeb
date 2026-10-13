import { RadioPills } from '@/components/form/radio-pills';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PromotionForm } from '@/hooks/use-promotion-form';
import { OCCASIONS, OTHER_OCCASION } from '@/lib/promotion-cards';
import type { PromotionMechanic } from './types';

const MAX_OVERLINE = 60;

const OFFERS = [
    { value: 'discount', label: 'Descuento' },
    { value: 'gift', label: 'Servicio de regalo' },
];

const CHOICES = [
    ...OCCASIONS.map(({ key, label }) => ({ key, label })),
    { key: OTHER_OCCASION, label: 'Otra' },
];

/** A campaign: the occasion (it names it and proposes its dates) and what it offers. */
export function OccasionField({ form }: { form: PromotionForm }) {
    const { data, setData, errors } = form;

    return (
        <div className="grid gap-5">
            <div
                role="radiogroup"
                aria-labelledby="promotion-occasion-legend"
                className="grid gap-2"
            >
                <p
                    id="promotion-occasion-legend"
                    className="text-sm font-medium"
                >
                    ¿Qué ocasión?
                </p>
                <div className="flex flex-wrap gap-2">
                    {CHOICES.map(({ key, label }) => (
                        <label key={key} className="cursor-pointer">
                            <input
                                type="radio"
                                name="occasion"
                                value={key}
                                checked={data.occasion === key}
                                onChange={() => form.pickOccasion(key)}
                                className="peer sr-only"
                            />
                            <span className="flex min-h-11 items-center rounded-full border bg-card px-4 text-sm transition-colors peer-checked:border-primary peer-checked:bg-brand-soft peer-checked:font-medium peer-focus-visible:ring-2 peer-focus-visible:ring-ring">
                                {label}
                            </span>
                        </label>
                    ))}
                </div>
                {data.occasion !== OTHER_OCCASION && (
                    <InputError message={errors.overline} />
                )}
            </div>

            {data.occasion === OTHER_OCCASION && (
                <div className="grid gap-2">
                    <Label htmlFor="promotion-overline">
                        Nombre de la campaña
                    </Label>
                    <Input
                        id="promotion-overline"
                        value={data.overline}
                        maxLength={MAX_OVERLINE}
                        placeholder="Ej. Aniversario del salón"
                        onChange={(event) =>
                            setData('overline', event.target.value)
                        }
                        aria-invalid={Boolean(errors.overline)}
                        className="h-11"
                    />
                    <InputError message={errors.overline} />
                </div>
            )}

            <RadioPills
                legend="¿Qué ofreces?"
                name="mechanic"
                options={OFFERS}
                value={data.mechanic}
                onChange={(mechanic) =>
                    setData('mechanic', mechanic as PromotionMechanic)
                }
            />
        </div>
    );
}
