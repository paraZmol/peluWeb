import { AmountInput } from '@/components/accounting/amount-input';
import { RadioPills } from '@/components/form/radio-pills';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PromotionForm } from '@/hooks/use-promotion-form';
import type { PromotionFields, PromotionLimits } from './types';

const KINDS = [
    { value: 'percent', label: 'Porcentaje' },
    { value: 'amount', label: 'Monto en soles' },
];

type Props = { form: PromotionForm; limits: PromotionLimits };

/** How much comes off: a percentage of each service, or soles once per booking. */
export function BenefitField({ form, limits }: Props) {
    const { data, setData, errors } = form;

    return (
        <div className="grid gap-3">
            <RadioPills
                legend="¿Cuánto menos?"
                name="benefit"
                options={KINDS}
                value={data.benefit}
                onChange={(benefit) =>
                    setData('benefit', benefit as PromotionFields['benefit'])
                }
            />
            {data.benefit === 'percent' ? (
                <div className="grid gap-2">
                    <Label htmlFor="promotion-percent">Porcentaje</Label>
                    <div className="relative w-32">
                        <Input
                            id="promotion-percent"
                            type="text"
                            inputMode="numeric"
                            autoComplete="off"
                            value={data.percent}
                            onChange={(event) =>
                                setData(
                                    'percent',
                                    event.target.value.replace(/\D/g, ''),
                                )
                            }
                            aria-invalid={Boolean(errors.percent)}
                            aria-describedby="promotion-percent-hint"
                            className="h-11 pr-9 text-lg tabular-nums"
                        />
                        <span
                            aria-hidden
                            className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-muted-foreground"
                        >
                            %
                        </span>
                    </div>
                    <p
                        id="promotion-percent-hint"
                        className="text-sm text-muted-foreground"
                    >
                        De {limits.minPercent} a {limits.maxPercent}. Se aplica
                        a cada servicio elegido.
                    </p>
                    <InputError message={errors.percent} />
                </div>
            ) : (
                <div className="grid gap-2">
                    <Label htmlFor="promotion-amount">Monto</Label>
                    <AmountInput
                        id="promotion-amount"
                        value={data.amount}
                        onChange={(amount) => setData('amount', amount)}
                        aria-invalid={Boolean(errors.amount)}
                        aria-describedby="promotion-amount-hint"
                        className="w-40 text-lg"
                    />
                    <p
                        id="promotion-amount-hint"
                        className="text-sm text-muted-foreground"
                    >
                        Se descuenta una vez por reserva. Debe ser menor que el
                        servicio elegido más barato.
                    </p>
                    <InputError message={errors.amount} />
                </div>
            )}
        </div>
    );
}
