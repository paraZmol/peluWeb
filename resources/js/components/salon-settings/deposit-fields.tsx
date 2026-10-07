import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type DepositValues = {
    deposit_percent: number;
    /** Text so it can be left blank ("same as everyone"). */
    vip_deposit_percent: string;
    hold_minutes: number;
    payment_instructions: string;
};

type Props = {
    values: DepositValues;
    errors: Partial<Record<keyof DepositValues, string>>;
    onChange: <K extends keyof DepositValues>(
        key: K,
        value: DepositValues[K],
    ) => void;
};

/** How much is asked in advance, for how long the slot waits, and where to pay. */
export function DepositFields({ values, errors, onChange }: Props) {
    return (
        <fieldset className="grid gap-4 rounded-lg border p-4">
            <legend className="px-1 text-sm font-medium">Seña</legend>
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-1">
                    <Label htmlFor="booking-deposit">Seña (%)</Label>
                    <Input
                        id="booking-deposit"
                        type="number"
                        inputMode="numeric"
                        min={0}
                        max={100}
                        value={values.deposit_percent}
                        onChange={(e) =>
                            onChange('deposit_percent', Number(e.target.value))
                        }
                    />
                    <span className="text-xs text-muted-foreground">
                        0 = sin seña: se confirma al instante
                    </span>
                    <InputError message={errors.deposit_percent} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="booking-vip-deposit">
                        Seña para VIP (%)
                    </Label>
                    <Input
                        id="booking-vip-deposit"
                        type="number"
                        inputMode="numeric"
                        min={0}
                        max={100}
                        placeholder="Igual"
                        value={values.vip_deposit_percent}
                        onChange={(e) =>
                            onChange('vip_deposit_percent', e.target.value)
                        }
                    />
                    <span className="text-xs text-muted-foreground">
                        En blanco = la misma seña
                    </span>
                    <InputError message={errors.vip_deposit_percent} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="booking-hold">Espera (minutos)</Label>
                    <Input
                        id="booking-hold"
                        type="number"
                        inputMode="numeric"
                        min={10}
                        value={values.hold_minutes}
                        onChange={(e) =>
                            onChange('hold_minutes', Number(e.target.value))
                        }
                    />
                    <span className="text-xs text-muted-foreground">
                        Tiempo para enviar el comprobante
                    </span>
                    <InputError message={errors.hold_minutes} />
                </div>
            </div>
            <div className="grid gap-1">
                <Label htmlFor="booking-payment">
                    Datos para recibir la seña
                </Label>
                <Textarea
                    id="booking-payment"
                    className="min-h-16"
                    maxLength={255}
                    placeholder="Ej. Yape o Plin al 987 654 321, a nombre de Luis Quispe"
                    value={values.payment_instructions}
                    onChange={(e) =>
                        onChange('payment_instructions', e.target.value)
                    }
                />
                <InputError message={errors.payment_instructions} />
            </div>
        </fieldset>
    );
}
