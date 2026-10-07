import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { HolidaysSection } from '@/components/staff/holidays-section';
import { StaffCard } from '@/components/staff/staff-card';
import { StaffForm } from '@/components/staff/staff-form';
import type {
    Holiday,
    LinkableMember,
    StaffSummary,
} from '@/components/staff/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index } from '@/routes/staff';
import { index as team } from '@/routes/team';

type Props = {
    staff: StaffSummary[];
    linkableMembers: LinkableMember[];
    holidays: Holiday[];
    today: string;
};

export default function StaffIndex({
    staff,
    linkableMembers,
    holidays,
    today,
}: Props) {
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title="Profesionales" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 px-4 py-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Profesionales
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Quienes atienden y en qué horario. No necesitan
                            tener cuenta: para darles acceso a la app, invítalos
                            desde{' '}
                            <Link href={team()} className="underline">
                                Equipo
                            </Link>
                            .
                        </p>
                    </div>
                    <Dialog open={creating} onOpenChange={setCreating}>
                        <DialogTrigger asChild>
                            <Button className="rounded-full">
                                <Plus /> Agregar profesional
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[90svh] overflow-y-auto">
                            <DialogHeader>
                                <DialogTitle>Nuevo profesional</DialogTitle>
                                <DialogDescription>
                                    Después eliges sus días y horas.
                                </DialogDescription>
                            </DialogHeader>
                            <StaffForm
                                linkableMembers={linkableMembers}
                                onSaved={() => setCreating(false)}
                            />
                        </DialogContent>
                    </Dialog>
                </div>

                {staff.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Aún no hay nadie. Agrega a quien atiende (puedes ser tú)
                        para definir el horario del salón.
                    </p>
                ) : (
                    <ul className="grid gap-3">
                        {staff.map((person) => (
                            <StaffCard
                                key={person.id}
                                staff={person}
                                linkableMembers={linkableMembers}
                            />
                        ))}
                    </ul>
                )}

                <HolidaysSection holidays={holidays} today={today} />
            </div>
        </>
    );
}

StaffIndex.layout = {
    breadcrumbs: [{ title: 'Profesionales', href: index() }],
};
