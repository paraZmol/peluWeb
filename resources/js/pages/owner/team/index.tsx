import { Head } from '@inertiajs/react';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { InviteMemberForm } from '@/components/team/invite-member-form';
import { MemberList } from '@/components/team/member-list';
import type {
    PendingInvitation,
    TeamMember,
    UnlinkedStaff,
} from '@/components/team/types';
import { index as team } from '@/routes/team';
import { destroy as cancelInvitation } from '@/routes/team/invitations';
import type { Option, SalonPermission, SalonRole } from '@/types';

type Props = {
    members: TeamMember[];
    invitations: PendingInvitation[];
    unlinkedStaff: UnlinkedStaff[];
    roles: Option<SalonRole>[];
    permissionOptions: Option<SalonPermission>[];
};

const dateFormat = new Intl.DateTimeFormat('es-PE', {
    day: 'numeric',
    month: 'short',
});

export default function Team({
    members,
    invitations,
    unlinkedStaff,
    roles,
    permissionOptions,
}: Props) {
    return (
        <>
            <Head title="Equipo" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-10 px-4 py-6">
                <section className="flex flex-col gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Tu equipo</h1>
                        <p className="text-sm text-muted-foreground">
                            Quienes atienden en tu salón y lo que puede hacer
                            cada persona.
                        </p>
                    </div>
                    <MemberList
                        members={members}
                        roles={roles}
                        permissionOptions={permissionOptions}
                    />
                </section>

                {invitations.length > 0 && (
                    <section className="flex flex-col gap-3">
                        <h2 className="text-lg font-semibold">
                            Invitaciones pendientes
                        </h2>
                        <ul className="divide-y rounded-xl border bg-card">
                            {invitations.map((invitation) => (
                                <li
                                    key={invitation.id}
                                    className="flex items-center gap-3 p-4"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate">
                                            {invitation.email}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Vence el{' '}
                                            {dateFormat.format(
                                                new Date(invitation.expiresAt),
                                            )}
                                        </p>
                                    </div>
                                    <ConfirmRemoveDialog
                                        triggerLabel="Cancelar"
                                        title="¿Cancelar esta invitación?"
                                        description="El enlace que recibió dejará de funcionar."
                                        confirmLabel="Sí, cancelar"
                                        form={cancelInvitation.form(
                                            invitation.id,
                                        )}
                                    />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="flex flex-col gap-4 rounded-xl border bg-card p-5">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Invitar a alguien
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Le llegará un correo con un enlace para unirse.
                        </p>
                    </div>
                    <InviteMemberForm
                        roles={roles}
                        permissionOptions={permissionOptions}
                        unlinkedStaff={unlinkedStaff}
                    />
                </section>
            </div>
        </>
    );
}

Team.layout = {
    breadcrumbs: [{ title: 'Equipo', href: team() }],
};
