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
import { store, update } from '@/routes/staff';
import type { LinkableMember, StaffSummary } from './types';

const NO_ACCOUNT = 'none';

type Props = {
    staff?: StaffSummary;
    /** Team accounts not linked to any professional yet. */
    linkableMembers: LinkableMember[];
    onSaved: () => void;
};

export function StaffForm({ staff, linkableMembers, onSaved }: Props) {
    const form = useForm({
        name: staff?.name ?? '',
        title: staff?.title ?? '',
        is_active: staff?.isActive ?? true,
        user_id: staff?.userId ?? null,
    });
    const { data, setData, errors, processing } = form;
    const accounts = [
        ...(staff?.userId && staff.accountEmail
            ? [
                  {
                      userId: staff.userId,
                      name: staff.name,
                      email: staff.accountEmail,
                  },
              ]
            : []),
        ...linkableMembers,
    ];

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.submit(staff ? update(staff.id) : store(), {
            preserveScroll: true,
            onSuccess: onSaved,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-5">
            <div className="grid gap-2">
                <Label htmlFor="staff-name">Nombre</Label>
                <Input
                    id="staff-name"
                    value={data.name}
                    maxLength={80}
                    autoComplete="off"
                    placeholder="Como lo verán tus clientes"
                    onChange={(e) => setData('name', e.target.value)}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="staff-title">Especialidad (opcional)</Label>
                <Input
                    id="staff-title"
                    value={data.title}
                    maxLength={60}
                    placeholder="Ej. Barbería y fades, Colorista, Manicurista"
                    onChange={(e) => setData('title', e.target.value)}
                />
                <InputError message={errors.title} />
            </div>

            {accounts.length > 0 && (
                <div className="grid gap-2">
                    <Label>Cuenta para entrar a la app</Label>
                    <Select
                        value={data.user_id?.toString() ?? NO_ACCOUNT}
                        onValueChange={(value) =>
                            setData(
                                'user_id',
                                value === NO_ACCOUNT ? null : Number(value),
                            )
                        }
                    >
                        <SelectTrigger aria-label="Cuenta para entrar a la app">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NO_ACCOUNT}>
                                Sin cuenta (tú manejas su horario)
                            </SelectItem>
                            {accounts.map((account) => (
                                <SelectItem
                                    key={account.userId}
                                    value={account.userId.toString()}
                                >
                                    {account.name} · {account.email}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">
                        Con una cuenta, esta persona puede ver y editar su
                        propio horario. Las invitaciones se envían desde Equipo.
                    </p>
                    <InputError message={errors.user_id} />
                </div>
            )}

            {staff && (
                <label className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm">
                    <Checkbox
                        checked={data.is_active}
                        onCheckedChange={(checked) =>
                            setData('is_active', checked === true)
                        }
                    />
                    Atiende clientes (aparece para reservar)
                </label>
            )}

            <Button
                type="submit"
                className="rounded-full"
                disabled={processing}
            >
                {processing && <Spinner />}
                {staff ? 'Guardar cambios' : 'Agregar y definir horario'}
            </Button>
        </form>
    );
}
