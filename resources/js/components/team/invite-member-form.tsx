import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PermissionChecklist } from '@/components/team/permission-checklist';
import { RolePicker } from '@/components/team/role-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/team/invitations';
import type { Option, SalonPermission, SalonRole } from '@/types';

type Props = {
    roles: Option<SalonRole>[];
    permissionOptions: Option<SalonPermission>[];
};

export function InviteMemberForm({ roles, permissionOptions }: Props) {
    const [role, setRole] = useState<SalonRole>('staff');

    return (
        <Form
            {...store.form()}
            resetOnSuccess
            onSuccess={() => setRole('staff')}
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
