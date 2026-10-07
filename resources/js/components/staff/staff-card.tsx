import { Link } from '@inertiajs/react';
import { CalendarClock, Pencil } from 'lucide-react';
import { useState } from 'react';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useInitials } from '@/hooks/use-initials';
import { WEEKDAYS } from '@/lib/schedule';
import { cn } from '@/lib/utils';
import { destroy } from '@/routes/staff';
import { show as schedule } from '@/routes/staff/schedule';
import { StaffForm } from './staff-form';
import type { LinkableMember, StaffSummary } from './types';

type Props = { staff: StaffSummary; linkableMembers: LinkableMember[] };

export function StaffCard({ staff, linkableMembers }: Props) {
    const [editing, setEditing] = useState(false);
    const getInitials = useInitials();
    const workDays = WEEKDAYS.filter((day) => staff.workDays.includes(day.iso));

    return (
        <li
            className={cn(
                'flex flex-col gap-4 rounded-xl border bg-card p-4',
                !staff.isActive && 'opacity-70',
            )}
        >
            <div className="flex items-center gap-3">
                <Avatar className="size-12">
                    <AvatarImage src={staff.photoUrl ?? undefined} alt="" />
                    <AvatarFallback>{getInitials(staff.name)}</AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1">
                    <p className="font-medium break-words">
                        {staff.name}
                        {!staff.isActive && (
                            <Badge variant="secondary" className="ml-2">
                                No atiende
                            </Badge>
                        )}
                    </p>
                    <p className="text-sm break-words text-muted-foreground">
                        {[
                            staff.title,
                            staff.accountEmail ?? 'Sin cuenta en la app',
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <ul
                    className="flex gap-1"
                    aria-label={
                        workDays.length > 0
                            ? `Atiende: ${workDays.map((day) => day.label).join(', ')}`
                            : 'Sin horario'
                    }
                >
                    {WEEKDAYS.map((day) => (
                        <li
                            key={day.iso}
                            aria-hidden
                            className={cn(
                                'flex size-7 items-center justify-center rounded-full text-xs font-medium',
                                staff.workDays.includes(day.iso)
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {day.initial}
                        </li>
                    ))}
                </ul>
                <span className="text-muted-foreground">
                    {workDays.length === 0
                        ? 'Aún sin horario'
                        : `${staff.servicesCount} ${staff.servicesCount === 1 ? 'servicio' : 'servicios'}`}
                </span>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <Button
                    asChild
                    size="sm"
                    variant={workDays.length === 0 ? 'default' : 'outline'}
                >
                    <Link href={schedule(staff.id)}>
                        <CalendarClock /> Horario y servicios
                    </Link>
                </Button>
                <Dialog open={editing} onOpenChange={setEditing}>
                    <DialogTrigger asChild>
                        <Button variant="outline" size="sm">
                            <Pencil /> Editar
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="max-h-[90svh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>{staff.name}</DialogTitle>
                            <DialogDescription>
                                Sus datos y la cuenta con la que entra.
                            </DialogDescription>
                        </DialogHeader>
                        <StaffForm
                            staff={staff}
                            linkableMembers={linkableMembers}
                            onSaved={() => setEditing(false)}
                        />
                    </DialogContent>
                </Dialog>
                <ConfirmRemoveDialog
                    triggerLabel="Quitar"
                    title={`¿Quitar a ${staff.name}?`}
                    description="Dejará de aparecer para reservar y se borrará su horario. Su cuenta, si tiene, sigue en el equipo."
                    confirmLabel="Sí, quitar"
                    form={destroy.form(staff.id)}
                />
            </div>
        </li>
    );
}
