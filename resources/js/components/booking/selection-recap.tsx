import { Scissors } from 'lucide-react';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { formatPrice } from '@/lib/format';

type Props = {
    wizard: BookingWizard;
    /** Shown once a professional was chosen (step "Día y hora"). */
    staffName: string | null;
};

/** What was picked so far, so the next question never loses its context. */
export function SelectionRecap({ wizard, staffName }: Props) {
    if (wizard.chosen.length === 0) {
        return null;
    }

    return (
        <div className="flex items-center gap-3 rounded-xl bg-muted/60 px-4 py-3">
            <span
                aria-hidden
                className="flex size-9 shrink-0 items-center justify-center rounded-full bg-background text-primary"
            >
                <Scissors className="size-4" />
            </span>
            <p className="min-w-0 flex-1 text-sm">
                <span className="line-clamp-2 font-medium break-words">
                    {wizard.chosen.map((service) => service.name).join(' + ')}
                </span>
                {staffName && (
                    <span className="block break-words text-muted-foreground">
                        Con {staffName}
                    </span>
                )}
            </p>
            <p className="shrink-0 text-right text-sm font-medium tabular-nums">
                <span className="block text-xs font-normal text-muted-foreground">
                    desde
                </span>
                {formatPrice(wizard.estimate)}
            </p>
        </div>
    );
}
