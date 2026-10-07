import type {
    AgendaBooking,
    BookingDay,
    StaffOption,
} from '@/components/booking/types';
import { BrandTheme } from '@/components/salon/brand-theme';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCurrentSalon } from '@/hooks/use-current-salon';
import { formatDate } from '@/lib/format';
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import { AgendaItem } from './agenda-item';

type Props = {
    /** The visit that was tapped in the grid; none closes the dialog. */
    booking: AgendaBooking | undefined;
    days: BookingDay[];
    staffOptions: StaffOption[];
    onClose: () => void;
};

/** One visit of the time grid with everything the list shows about it. */
export function AgendaBookingDialog({
    booking,
    days,
    staffOptions,
    onClose,
}: Props) {
    const { currentSalon } = useCurrentSalon();

    return (
        <Dialog
            open={booking !== undefined}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className={cn(SHEET_ON_PHONE, 'overflow-y-auto')}>
                {/* Dialogs render outside the page: hand the salon's color over. */}
                <BrandTheme
                    brand={currentSalon?.brand ?? null}
                    className="contents"
                >
                    <DialogHeader className="pr-8 text-left">
                        <DialogTitle>Cita</DialogTitle>
                        <DialogDescription className="capitalize">
                            {booking && formatDate(booking.date)}
                        </DialogDescription>
                    </DialogHeader>
                    {booking && (
                        <AgendaItem
                            booking={booking}
                            showStaff
                            days={days}
                            staffOptions={staffOptions}
                        />
                    )}
                </BrandTheme>
            </DialogContent>
        </Dialog>
    );
}
