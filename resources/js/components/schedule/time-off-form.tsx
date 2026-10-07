import { useForm } from '@inertiajs/react';
import { RadioPills } from '@/components/form/radio-pills';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/staff/timeoff';
import type { Option } from '@/types';
import { DateRangeFields } from './date-range-fields';
import { TimeRangePicker } from './time-range-picker';

type Props = {
    staffId: number;
    /** "Y-m-d" on the salon's clock. */
    today: string;
    reasons: Option[];
    onSaved: () => void;
};

export function TimeOffForm({ staffId, today, reasons, onSaved }: Props) {
    const form = useForm({
        starts_on: today,
        ends_on: today,
        all_day: true,
        start_time: '13:00',
        end_time: '15:00',
        reason: reasons[0]?.value ?? '',
        note: '',
    });
    const { data, setData, errors, processing } = form;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.submit(store(staffId), {
            preserveScroll: true,
            onSuccess: onSaved,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-5">
            <DateRangeFields
                today={today}
                from={data.starts_on}
                to={data.ends_on}
                error={errors.starts_on ?? errors.ends_on}
                onChange={(from, to) =>
                    setData((current) => ({
                        ...current,
                        starts_on: from,
                        ends_on: to,
                    }))
                }
            />

            <label className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm">
                <Checkbox
                    checked={data.all_day}
                    onCheckedChange={(checked) =>
                        setData('all_day', checked === true)
                    }
                />
                Todo el día
            </label>

            {!data.all_day && (
                <div>
                    <TimeRangePicker
                        start={data.start_time}
                        end={data.end_time}
                        startLabel="Sale"
                        endLabel="Regresa"
                        onChange={(start, end) =>
                            setData((current) => ({
                                ...current,
                                start_time: start,
                                end_time: end,
                            }))
                        }
                    />
                    <InputError
                        message={errors.start_time ?? errors.end_time}
                    />
                </div>
            )}

            <div>
                <RadioPills
                    legend="Motivo"
                    name="reason"
                    options={reasons}
                    value={data.reason}
                    onChange={(reason) => setData('reason', reason)}
                />
                <InputError message={errors.reason} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="time-off-note">Nota (opcional)</Label>
                <Input
                    id="time-off-note"
                    maxLength={120}
                    placeholder="Ej. cita médica"
                    value={data.note}
                    onChange={(e) => setData('note', e.target.value)}
                />
                <InputError message={errors.note} />
            </div>

            <Button
                type="submit"
                className="rounded-full"
                disabled={processing}
            >
                {processing && <Spinner />}
                Guardar ausencia
            </Button>
        </form>
    );
}
