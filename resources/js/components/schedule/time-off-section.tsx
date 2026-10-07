import { CalendarOff, Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
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
import { formatDate, formatTime } from '@/lib/format';
import { destroy } from '@/routes/staff/timeoff';
import type { Option } from '@/types';
import { TimeOffForm } from './time-off-form';
import type { TimeOffItem } from './types';

type Props = {
    staffId: number;
    items: TimeOffItem[];
    reasons: Option[];
    today: string;
};

function describe(item: TimeOffItem): string {
    const sameDay = item.startsOn === item.endsOn;

    if (item.allDay) {
        return sameDay
            ? `${formatDate(item.startsOn)} · todo el día`
            : `Del ${formatDate(item.startsOn)} al ${formatDate(item.endsOn)}`;
    }

    return sameDay
        ? `${formatDate(item.startsOn)} · ${formatTime(item.startTime)} – ${formatTime(item.endTime)}`
        : `Del ${formatDate(item.startsOn)} ${formatTime(item.startTime)} al ${formatDate(item.endsOn)} ${formatTime(item.endTime)}`;
}

export function TimeOffSection({ staffId, items, reasons, today }: Props) {
    const [adding, setAdding] = useState(false);

    return (
        <div className="flex flex-col gap-4">
            <Dialog open={adding} onOpenChange={setAdding}>
                <DialogTrigger asChild>
                    <Button className="self-start rounded-full">
                        <Plus /> Agregar ausencia
                    </Button>
                </DialogTrigger>
                <DialogContent className="max-h-[92svh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Nueva ausencia</DialogTitle>
                        <DialogDescription>
                            En esas fechas no se podrá reservar con esta
                            persona.
                        </DialogDescription>
                    </DialogHeader>
                    <TimeOffForm
                        staffId={staffId}
                        today={today}
                        reasons={reasons}
                        onSaved={() => setAdding(false)}
                    />
                </DialogContent>
            </Dialog>

            {items.length === 0 ? (
                <p className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                    <CalendarOff className="size-6" aria-hidden />
                    Sin ausencias programadas. Aquí van vacaciones, permisos o
                    un bloqueo de unas horas.
                </p>
            ) : (
                <ul className="divide-y rounded-xl border bg-card">
                    {items.map((item) => (
                        <li
                            key={item.id}
                            className="flex items-center gap-3 p-4"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="font-medium tabular-nums">
                                    {describe(item)}
                                </p>
                                <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <Badge variant="secondary">
                                        {item.reason}
                                    </Badge>
                                    <span className="break-words">
                                        {item.note}
                                    </span>
                                </p>
                            </div>
                            <ConfirmRemoveDialog
                                triggerLabel="Eliminar"
                                title="¿Eliminar esta ausencia?"
                                description="Esas fechas volverán a estar disponibles para reservar."
                                confirmLabel="Sí, eliminar"
                                form={destroy.form({
                                    staff: staffId,
                                    timeOff: item.id,
                                })}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
