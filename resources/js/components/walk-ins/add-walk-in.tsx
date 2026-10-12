import { useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store } from '@/routes/walk-ins';
import type { WalkInOption } from './types';

/** "Anyone" and "not sure yet" in the two optional lists. */
const NONE = 'none';

type Props = {
    services: WalkInOption[];
    staff: WalkInOption[];
    nameMaxLength: number;
};

function Choice({
    id,
    label,
    placeholder,
    options,
    value,
    onChange,
}: {
    id: string;
    label: string;
    placeholder: string;
    options: WalkInOption[];
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {label}{' '}
                <span className="font-normal text-muted-foreground">
                    (opcional)
                </span>
            </Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className="h-11 w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NONE}>{placeholder}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.id} value={String(option.id)}>
                            {option.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

/** Writes down who just walked in: a name is enough. */
export function AddWalkIn({ services, staff, nameMaxLength }: Props) {
    const form = useForm({ name: '', service_id: NONE, staff_id: NONE });
    const { data, setData, errors, processing } = form;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((fields) => ({
            name: fields.name,
            service_id: fields.service_id === NONE ? null : fields.service_id,
            staff_id: fields.staff_id === NONE ? null : fields.staff_id,
        }));
        form.submit(store(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-3 sm:items-end"
        >
            <div className="grid gap-2">
                <Label htmlFor="walk-in-name">Nombre</Label>
                <Input
                    id="walk-in-name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    maxLength={nameMaxLength}
                    autoComplete="off"
                    className="h-11"
                    aria-invalid={Boolean(errors.name)}
                    aria-describedby={
                        errors.name ? 'walk-in-name-error' : undefined
                    }
                />
            </div>
            <Choice
                id="walk-in-service"
                label="Servicio"
                placeholder="Todavía no sabe"
                options={services}
                value={data.service_id}
                onChange={(value) => setData('service_id', value)}
            />
            <Choice
                id="walk-in-staff"
                label="Con quién"
                placeholder="Quien esté libre"
                options={staff}
                value={data.staff_id}
                onChange={(value) => setData('staff_id', value)}
            />
            <div className="grid gap-2 sm:col-span-3">
                <InputError id="walk-in-name-error" message={errors.name} />
                <InputError message={errors.service_id ?? errors.staff_id} />
                <Button
                    type="submit"
                    className="h-11 sm:justify-self-start"
                    disabled={processing}
                >
                    <UserPlus aria-hidden /> Anotar en la fila
                </Button>
            </div>
        </form>
    );
}
