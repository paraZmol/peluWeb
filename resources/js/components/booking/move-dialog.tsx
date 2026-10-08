import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { BrandTheme } from '@/components/salon/brand-theme';
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
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BrandTokens } from '@/types';
import { DayStrip } from './day-strip';
import { SlotGrid } from './slot-grid';
import { StaffSelect } from './staff-select';
import type { BookingDay, Slot } from './types';

type Props = {
    trigger: ReactNode;
    title: string;
    description: string;
    /** "Mover" for the team, "Cambiar" for the client. */
    confirmVerb?: string;
    days: BookingDay[];
    /** The day the booking is on now: where the picker opens. */
    date: string;
    slotsUrl: (date: string, staffId: number | null) => string;
    moveUrl: string;
    /** Colleagues it can be handed to; only for whoever runs every agenda. */
    staffOptions?: { id: number; name: string }[];
    staffId?: number;
    /** Dialogs render outside the page: the salon's color is handed over. */
    brand?: BrandTokens | null;
};

/** Pick another day and time for an existing booking. */
export function MoveDialog({
    trigger,
    title,
    description,
    confirmVerb = 'Mover',
    days,
    date: currentDate,
    slotsUrl,
    moveUrl,
    staffOptions = [],
    staffId: currentStaffId,
    brand = null,
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
            <DialogContent
                className={cn(
                    SHEET_ON_PHONE,
                    'grid-rows-[auto_1fr_auto] overflow-hidden',
                )}
            >
                <BrandTheme brand={brand} className="contents">
                    <DialogHeader className="pr-8 text-left">
                        <DialogTitle className="leading-tight">
                            {title}
                        </DialogTitle>
                        <DialogDescription className="break-words">
                            {description}
                        </DialogDescription>
                    </DialogHeader>

                    {/* Children must not shrink: the day strip would collapse. */}
                    <div className="-mx-6 flex min-h-0 flex-col gap-4 overflow-x-hidden overflow-y-auto px-6 py-1 *:shrink-0">
                        {staffOptions.length > 1 && staffId !== null && (
                            <StaffSelect
                                className="w-full data-[size=default]:h-11 sm:data-[size=default]:h-9"
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

                    <DialogFooter className="-mx-6 border-t px-6 pt-4">
                        <Button
                            className="h-12 rounded-full px-6 tabular-nums sm:h-11"
                            disabled={slot === null || sending}
                            onClick={move}
                        >
                            {sending && <Spinner />}
                            {slot
                                ? `${confirmVerb} a las ${formatTime(slot.time)}`
                                : 'Elige un horario'}
                        </Button>
                    </DialogFooter>
                </BrandTheme>
            </DialogContent>
        </Dialog>
    );
}
