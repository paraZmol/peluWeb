import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { PermissionChecklist } from '@/components/team/permission-checklist';
import { RolePicker } from '@/components/team/role-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/team/members';
import type { Option, SalonPermission, SalonRole } from '@/types';
import type { TeamMember } from './types';

type Props = {
    member: TeamMember;
    roles: Option<SalonRole>[];
    permissionOptions: Option<SalonPermission>[];
};

export function MemberEditDialog({ member, roles, permissionOptions }: Props) {
    const [open, setOpen] = useState(false);
    const [role, setRole] = useState<SalonRole>(member.role);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Editar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{member.name}</DialogTitle>
                    <DialogDescription>
                        Cambia su rol o lo que puede hacer en el salón.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...update.form(member.id)}
                    onSuccess={() => setOpen(false)}
                    className="flex flex-col gap-6"
                >
                    {({ processing }) => (
                        <>
                            <RolePicker
                                options={roles}
                                value={role}
                                onChange={setRole}
                            />
                            {role === 'staff' && (
                                <PermissionChecklist
                                    options={permissionOptions}
                                    defaultValues={member.permissions}
                                    idPrefix={`member-${member.id}`}
                                />
                            )}
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    className="rounded-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Guardar cambios
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
