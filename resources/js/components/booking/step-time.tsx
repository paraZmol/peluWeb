import { Tag } from 'lucide-react';
import { useEffect } from 'react';
import type { useAvailability } from '@/hooks/use-availability';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { limitedPromotionHint } from '@/lib/promo-offers';
import { CHIP_ACTION } from '@/lib/skin-actions';
import { DayStrip, toDate } from './day-strip';
import { SlotGrid } from './slot-grid';
import type { BookingDay, Viewer } from './types';
import { WaitlistOffer } from './waitlist-offer';

const monthFormat = new Intl.DateTimeFormat('es-PE', { month: 'long' });

type Props = {
    days: BookingDay[];
    wizard: BookingWizard;
    availability: ReturnType<typeof useAvailability>;
    /** Online booking only: a full day offers its waiting list. */
    waitlist?: { salonSlug: string; viewer: Viewer };
};

export function StepTime({ days, wizard, availability, waitlist }: Props) {
    const firstOpen = days.find((day) => !day.closed)?.date ?? null;
    const date = wizard.date ?? firstOpen;
    const { slots, error, isLoading, retry } = availability;
    const { chooseDate } = wizard;
    const promotionHint = limitedPromotionHint(
        wizard.promotions,
        wizard.chosen.map((service) => service.id),
    );

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

    const nextOpen =
        days.find((day) => !day.closed && date !== null && day.date > date)
            ?.date ?? null;

    return (
        <div className="flex flex-col gap-6">
            {wizard.notice && (
                <p
                    role="alert"
                    className="rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm"
                >
                    {wizard.notice}
                </p>
            )}
            {promotionHint && (
                <p className="flex items-start gap-2 text-sm text-muted-foreground">
                    <Tag
                        className="mt-0.5 size-4 shrink-0 text-brand-text"
                        aria-hidden
                    />
                    {promotionHint}
                </p>
            )}
            <div className="flex flex-col gap-2">
                <p className="flex items-baseline justify-between gap-3 text-sm">
                    <span className="font-bold first-letter:uppercase">
                        {monthFormat.format(toDate(date ?? firstOpen))}
                    </span>
                    <span className="text-muted-foreground sm:hidden">
                        Desliza para ver más días
                    </span>
                </p>
                <DayStrip
                    days={days}
                    value={date}
                    onChange={wizard.chooseDate}
                />
            </div>
            <SlotGrid
                slots={slots}
                error={error}
                isLoading={isLoading}
                retry={retry}
                onPick={wizard.chooseSlot}
                // Back from the summary: the time picked stays marked.
                selected={wizard.slot?.start ?? null}
                empty={
                    <>
                        <span>
                            No quedan horarios este día. Prueba con otro día
                            {wizard.staffId !== null && ' o con otra persona'}.
                        </span>
                        {nextOpen && (
                            <button
                                type="button"
                                className={CHIP_ACTION}
                                onClick={() => wizard.chooseDate(nextOpen)}
                            >
                                Ver el siguiente día
                            </button>
                        )}
                    </>
                }
            />
            {waitlist && date && (
                <WaitlistOffer
                    {...waitlist}
                    wizard={wizard}
                    date={date}
                    isFull={slots?.length === 0}
                    onStale={retry}
                />
            )}
        </div>
    );
}
