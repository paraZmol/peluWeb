import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
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
import { SLOTS_FAILURE } from '@/hooks/use-availability';
import { useFetched } from '@/hooks/use-fetched';
import { flashFailed } from '@/lib/flash';
import { formatTime } from '@/lib/format';
import { DayStrip } from './day-strip';
import { SlotGrid } from './slot-grid';
import { StaffSelect } from './staff-select';
import type { BookingDay, Slot } from './types';

type Props = {
    trigger: ReactNode;
    title: string;
    description: string;
    days: BookingDay[];
    /** The day the booking is on now: where the picker opens. */
    date: string;
    slotsUrl: (date: string, staffId: number | null) => string;
    moveUrl: string;
    /** Colleagues it can be handed to; only for whoever runs every agenda. */
    staffOptions?: { id: number; name: string }[];
    staffId?: number;
};

/** Pick another day and time for an existing booking. */
export function MoveDialog({
    trigger,
    title,
    description,
    days,
    date: currentDate,
    slotsUrl,
    moveUrl,
    staffOptions = [],
    staffId: currentStaffId,
}: Props) {
    const [open, setOpen] = useState(false);
    const [sending, setSending] = useState(false);
    const [slot, setSlot] = useState<Slot | null>(null);
    const [staffId, setStaffId] = useState(currentStaffId ?? null);
    const isOpenDay = (day: string) =>
        days.some((option) => option.date === day && !option.closed);
    const [date, setDate] = useState(
        isOpenDay(currentDate)
            ? currentDate
            : (days.find((day) => !day.closed)?.date ?? null),
    );
    const slots = useFetched<{ slots: Slot[] }>(
        open && date ? slotsUrl(date, staffId) : null,
        SLOTS_FAILURE,
    );

    const move = () =>
        slot &&
        router.post(
            moveUrl,
            { start: slot.start, staff: staffId },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
                onSuccess: (page) => {
                    setSlot(null);

                    // Taken meanwhile: show what is free now.
                    if (flashFailed(page)) {
                        slots.retry();
                    } else {
                        setOpen(false);
                    }
                },
            },
        );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90svh] grid-rows-[auto_1fr_auto] overflow-hidden">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <div className="-mx-6 flex min-h-0 flex-col gap-4 overflow-x-hidden overflow-y-auto px-6 py-1">
                    {staffOptions.length > 1 && staffId !== null && (
                        <StaffSelect
                            options={staffOptions}
                            value={staffId}
                            prefix="Con"
                            onChange={(id) => {
                                setStaffId(id);
                                setSlot(null);
                            }}
                        />
                    )}
                    <DayStrip
                        days={days}
                        value={date}
                        onChange={(day) => {
                            setDate(day);
                            setSlot(null);
                        }}
                    />
                    <SlotGrid
                        slots={slots.data?.slots ?? null}
                        error={slots.error}
                        isLoading={slots.isLoading}
                        retry={slots.retry}
                        onPick={setSlot}
                        selected={slot?.start ?? 0}
                        empty="No quedan horarios este día. Prueba con otro."
                    />
                </div>

                <DialogFooter>
                    <Button
                        className="h-11 rounded-full"
                        disabled={slot === null || sending}
                        onClick={move}
                    >
                        {sending && <Spinner />}
                        {slot
                            ? `Mover a las ${formatTime(slot.time)}`
                            : 'Elige un horario'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
