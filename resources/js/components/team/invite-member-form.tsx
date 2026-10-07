import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PermissionChecklist } from '@/components/team/permission-checklist';
import { RolePicker } from '@/components/team/role-picker';
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
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/team/invitations';
import type { Option, SalonPermission, SalonRole } from '@/types';
import type { UnlinkedStaff } from './types';

const NEW_PROFILE = 'new';

type Props = {
    roles: Option<SalonRole>[];
    permissionOptions: Option<SalonPermission>[];
    unlinkedStaff: UnlinkedStaff[];
};

export function InviteMemberForm({
    roles,
    permissionOptions,
    unlinkedStaff,
}: Props) {
    const [role, setRole] = useState<SalonRole>('staff');
    const [staffId, setStaffId] = useState(NEW_PROFILE);

    return (
        <Form
            {...store.form()}
            resetOnSuccess
            onSuccess={() => {
                setRole('staff');
                setStaffId(NEW_PROFILE);
            }}
            className="flex flex-col gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="invite-email">Correo electrónico</Label>
                        <Input
                            id="invite-email"
                            name="email"
                            type="email"
                            required
                            autoComplete="off"
                            placeholder="correo@ejemplo.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <RolePicker
                        options={roles}
                        value={role}
                        onChange={setRole}
                    />

                    {unlinkedStaff.length > 0 && (
                        <div className="grid gap-2">
                            <Label>¿Ya está entre tus profesionales?</Label>
                            <input
                                type="hidden"
                                name="staff_id"
                                value={staffId === NEW_PROFILE ? '' : staffId}
                            />
                            <Select value={staffId} onValueChange={setStaffId}>
                                <SelectTrigger aria-label="Profesional al que corresponde esta invitación">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NEW_PROFILE}>
                                        No, es alguien nuevo
                                    </SelectItem>
                                    {unlinkedStaff.map((person) => (
                                        <SelectItem
                                            key={person.id}
                                            value={person.id.toString()}
                                        >
                                            Sí, es {person.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-xs text-muted-foreground">
                                Así conserva el horario que ya le definiste.
                            </p>
                        </div>
                    )}

                    {role === 'staff' && (
                        <PermissionChecklist
                            options={permissionOptions}
                            idPrefix="invite"
                        />
                    )}

                    <Button
                        type="submit"
                        className="w-full rounded-full sm:w-auto sm:self-end"
                        disabled={processing}
                        data-test="invite-button"
                    >
                        {processing && <Spinner />}
                        Enviar invitación
                    </Button>
                </>
            )}
        </Form>
    );
}
