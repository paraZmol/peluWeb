import { useEffect } from 'react';
import type { useAvailability } from '@/hooks/use-availability';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { DayStrip } from './day-strip';
import { SlotGrid } from './slot-grid';
import type { BookingDay } from './types';

type Props = {
    days: BookingDay[];
    wizard: BookingWizard;
    availability: ReturnType<typeof useAvailability>;
};

export function StepTime({ days, wizard, availability }: Props) {
    const firstOpen = days.find((day) => !day.closed)?.date ?? null;
    const date = wizard.date ?? firstOpen;
    const { slots, error, isLoading, retry } = availability;
    const { chooseDate } = wizard;

    // Start on the first day the salon opens, so the list is never empty by default.
    useEffect(() => {
        if (wizard.date === null && firstOpen !== null) {
            chooseDate(firstOpen);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [wizard.date, firstOpen]);

    if (firstOpen === null) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                Por ahora no hay días disponibles para reservar.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-5">
            <DayStrip days={days} value={date} onChange={wizard.chooseDate} />
            <SlotGrid
                slots={slots}
                error={error}
                isLoading={isLoading}
                retry={retry}
                onPick={wizard.chooseSlot}
                empty={
                    <>
                        No quedan horarios este día. Prueba con otro día
                        {wizard.staffId !== null && ' o con otra persona'}.
                    </>
                }
            />
        </div>
    );
}
