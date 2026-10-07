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
import { update } from '@/routes/salon/booking';
import { DepositFields } from './deposit-fields';
import { SettingsSection } from './settings-section';
import type { SalonSettings } from './types';

type Props = { salon: SalonSettings; slotSteps: number[] };

const LEAD_OPTIONS = [
    { value: 0, label: 'Sin anticipación' },
    { value: 30, label: '30 minutos antes' },
    { value: 60, label: '1 hora antes' },
    { value: 120, label: '2 horas antes' },
    { value: 240, label: '4 horas antes' },
    { value: 1440, label: 'Un día antes' },
];

type Choice = { value: number; label: string };

export function BookingSection({ salon, slotSteps }: Props) {
    const { booking } = salon;
    const form = useForm({
        accepts_online_bookings: booking.acceptsOnlineBookings,
        min_lead_minutes: booking.minLeadMinutes,
        max_days_ahead: booking.maxDaysAhead,
        slot_step_minutes: booking.slotStepMinutes,
        cancel_window_hours: booking.cancelWindowHours,
        deposit_percent: booking.depositPercent,
        vip_deposit_percent: booking.vipDepositPercent?.toString() ?? '',
        hold_minutes: booking.holdMinutes,
        payment_instructions: booking.paymentInstructions ?? '',
    });
    const { data, setData, errors, processing } = form;
    // A value saved outside the presets still has to show up in the list.
    const leadOptions = LEAD_OPTIONS.some(
        (option) => option.value === data.min_lead_minutes,
    )
        ? LEAD_OPTIONS
        : [
              ...LEAD_OPTIONS,
              {
                  value: data.min_lead_minutes,
                  label: `${data.min_lead_minutes} minutos antes`,
              },
          ];

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((values) => ({
            ...values,
            vip_deposit_percent:
                values.vip_deposit_percent === ''
                    ? null
                    : values.vip_deposit_percent,
        }));
        form.submit(update(), { preserveScroll: true });
    };

    const select = (
        key: 'min_lead_minutes' | 'slot_step_minutes',
        label: string,
        options: Choice[],
    ) => (
        <div className="grid gap-1">
            <Label>{label}</Label>
            <Select
                value={data[key].toString()}
                onValueChange={(value) => setData(key, Number(value))}
            >
                <SelectTrigger aria-label={label}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem
                            key={option.value}
                            value={option.value.toString()}
                        >
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={errors[key]} />
        </div>
    );

    const number = (
        key: 'max_days_ahead' | 'cancel_window_hours',
        label: string,
        hint: string,
    ) => (
        <div className="grid gap-1">
            <Label htmlFor={`booking-${key}`}>{label}</Label>
            <Input
                id={`booking-${key}`}
                type="number"
                inputMode="numeric"
                min={0}
                value={data[key]}
                onChange={(e) => setData(key, Number(e.target.value))}
            />
            <span className="text-xs text-muted-foreground">{hint}</span>
            <InputError message={errors[key]} />
        </div>
    );

    return (
        <SettingsSection
            id="reservas"
            title="Reservas en línea"
            description="Cómo y cuándo pueden reservar tus clientes desde tu página."
        >
            <form onSubmit={submit} noValidate className="flex flex-col gap-6">
                <label className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm">
                    <Checkbox
                        checked={data.accepts_online_bookings}
                        onCheckedChange={(checked) =>
                            setData('accepts_online_bookings', checked === true)
                        }
                    />
                    Recibir reservas desde mi página
                </label>

                <div className="grid gap-4 sm:grid-cols-2">
                    {select(
                        'min_lead_minutes',
                        'Se puede reservar hasta',
                        leadOptions,
                    )}
                    {select(
                        'slot_step_minutes',
                        'Horarios cada',
                        slotSteps.map((step) => ({
                            value: step,
                            label: `${step} minutos`,
                        })),
                    )}
                    {number(
                        'max_days_ahead',
                        'Días de anticipación',
                        'Hasta cuántos días adelante se puede reservar',
                    )}
                    {number(
                        'cancel_window_hours',
                        'Plazo para cancelar (horas)',
                        'Quien cancela después pierde la seña',
                    )}
                </div>

                <DepositFields
                    values={data}
                    errors={errors}
                    onChange={(key, value) =>
                        setData((current) => ({ ...current, [key]: value }))
                    }
                />

                <Button
                    type="submit"
                    className="rounded-full sm:self-end"
                    disabled={processing}
                >
                    {processing && <Spinner />}
                    Guardar reglas
                </Button>
            </form>
        </SettingsSection>
    );
}
