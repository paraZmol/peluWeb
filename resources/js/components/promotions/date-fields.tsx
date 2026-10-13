import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PromotionForm } from '@/hooks/use-promotion-form';
import { addDays } from '@/lib/promotions';

type Props = {
    form: PromotionForm;
    today: string;
    /** The longest a promotion may run. */
    maxDays: number;
};

/** From which visit to which one it is valid: by the date of the visit. */
export function DateFields({ form, today, maxDays }: Props) {
    const { data, setData, errors } = form;

    return (
        <div className="grid gap-2">
            <div className="grid grid-cols-2 gap-3">
                <div className="grid gap-2">
                    <Label htmlFor="promotion-starts-on">Desde</Label>
                    <Input
                        id="promotion-starts-on"
                        type="date"
                        min={today}
                        value={data.starts_on}
                        onChange={(event) =>
                            setData('starts_on', event.target.value)
                        }
                        aria-invalid={Boolean(errors.starts_on)}
                        className="h-11"
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="promotion-ends-on">Hasta</Label>
                    <Input
                        id="promotion-ends-on"
                        type="date"
                        min={data.starts_on || today}
                        max={
                            data.starts_on
                                ? addDays(data.starts_on, maxDays)
                                : undefined
                        }
                        value={data.ends_on}
                        onChange={(event) =>
                            setData('ends_on', event.target.value)
                        }
                        aria-invalid={Boolean(errors.ends_on)}
                        aria-describedby="promotion-dates-hint"
                        className="h-11"
                    />
                </div>
            </div>
            <p
                id="promotion-dates-hint"
                className="text-sm text-muted-foreground"
            >
                Cuenta la fecha de la cita, no el día en que se reserva. Como
                máximo {maxDays} días.
            </p>
            <InputError message={errors.starts_on} />
            <InputError message={errors.ends_on} />
        </div>
    );
}
