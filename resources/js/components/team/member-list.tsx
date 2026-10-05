import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { MemberEditDialog } from '@/components/team/member-edit-dialog';
import { Badge } from '@/components/ui/badge';
import { destroy } from '@/routes/team/members';
import type { Option, SalonPermission, SalonRole } from '@/types';
import type { TeamMember } from './types';

type Props = {
    members: TeamMember[];
    roles: Option<SalonRole>[];
    permissionOptions: Option<SalonPermission>[];
};

export function MemberList({ members, roles, permissionOptions }: Props) {
    const reduceMotion = useReducedMotion();
    const roleLabel = (role: SalonRole) =>
        roles.find((option) => option.value === role)?.label ?? role;

    return (
        <ul className="divide-y rounded-xl border bg-card">
            <AnimatePresence initial={false}>
                {members.map((member) => (
                    <motion.li
                        key={member.id}
                        layout={!reduceMotion}
                        initial={reduceMotion ? false : { opacity: 0, y: 6 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        className="flex flex-col gap-3 p-4 md:flex-row md:items-center"
                    >
                        <div className="min-w-0 flex-1">
                            <p className="font-medium break-words">
                                {member.name}
                                {member.isYou && (
                                    <span className="ml-2 text-sm text-muted-foreground">
                                        (tú)
                                    </span>
                                )}
                            </p>
                            <p className="text-sm break-all text-muted-foreground">
                                {member.email}
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <Badge
                                variant={
                                    member.role === 'owner'
                                        ? 'default'
                                        : 'secondary'
                                }
                            >
                                {roleLabel(member.role)}
                            </Badge>
                            <div className="ml-auto flex gap-3 md:gap-1">
                                <MemberEditDialog
                                    member={member}
                                    roles={roles}
                                    permissionOptions={permissionOptions}
                                />
                                {!member.isYou && (
                                    <ConfirmRemoveDialog
                                        triggerLabel="Quitar"
                                        title={`¿Quitar a ${member.name} del equipo?`}
                                        description="Ya no podrá entrar a este salón. Sus datos personales no se borran."
                                        confirmLabel="Sí, quitar"
                                        form={destroy.form(member.id)}
                                    />
                                )}
                            </div>
                        </div>
                    </motion.li>
                ))}
            </AnimatePresence>
        </ul>
    );
}
