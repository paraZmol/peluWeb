import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PromotionForm } from '@/hooks/use-promotion-form';

/** 1 = Monday … 7 = Sunday, as the server counts them. */
const WEEKDAYS = [
    { day: 1, short: 'L', name: 'Lunes' },
    { day: 2, short: 'M', name: 'Martes' },
    { day: 3, short: 'X', name: 'Miércoles' },
    { day: 4, short: 'J', name: 'Jueves' },
    { day: 5, short: 'V', name: 'Viernes' },
    { day: 6, short: 'S', name: 'Sábado' },
    { day: 7, short: 'D', name: 'Domingo' },
];

type Props = {
    form: PromotionForm;
    /** "Horas flojas": days and hours are what the promotion is about. */
    required: boolean;
};

/** The days and the hours it is valid on. The hour the visit starts counts. */
export function WhenFields({ form, required }: Props) {
    const { data, setData, errors } = form;
    const optional = required ? '' : ' (opcional)';

    return (
        <div className="grid gap-4">
            <div
                role="group"
                aria-labelledby="promotion-weekdays-legend"
                className="grid gap-2"
            >
                <p
                    id="promotion-weekdays-legend"
                    className="text-sm font-medium"
                >
                    Días{optional}
                </p>
                <div className="grid grid-cols-7 gap-1.5">
                    {WEEKDAYS.map(({ day, short, name }) => (
                        <label key={day} className="cursor-pointer">
                            <input
                                type="checkbox"
                                aria-label={name}
                                checked={data.weekdays.includes(day)}
                                onChange={() => form.toggleWeekday(day)}
                                className="peer sr-only"
                            />
                            <span
                                aria-hidden
                                className="flex h-11 items-center justify-center rounded-xl border bg-card text-sm font-medium transition-colors peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-background"
                            >
                                {short}
                            </span>
                        </label>
                    ))}
                </div>
                {!required && (
                    <p className="text-sm text-muted-foreground">
                        Sin marcar ninguno, vale todos los días.
                    </p>
                )}
                <InputError message={errors.weekdays} />
            </div>

            <div className="grid gap-2">
                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-2">
                        <Label htmlFor="promotion-from-time">
                            Desde las{optional}
                        </Label>
                        <Input
                            id="promotion-from-time"
                            type="time"
                            step={300}
                            value={data.from_time}
                            onChange={(event) =>
                                setData('from_time', event.target.value)
                            }
                            aria-invalid={Boolean(errors.from_time)}
                            className="h-11"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="promotion-to-time">
                            Hasta las{optional}
                        </Label>
                        <Input
                            id="promotion-to-time"
                            type="time"
                            step={300}
                            value={data.to_time}
                            onChange={(event) =>
                                setData('to_time', event.target.value)
                            }
                            aria-invalid={Boolean(errors.to_time)}
                            className="h-11"
                        />
                    </div>
                </div>
                <p className="text-sm text-muted-foreground">
                    Cuenta la hora en que empieza la cita.
                </p>
                <InputError message={errors.from_time} />
                <InputError message={errors.to_time} />
            </div>
        </div>
    );
}
