import { Sparkles } from 'lucide-react';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { formatPrice } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    wizard: BookingWizard;
    /** Shown once a professional was chosen (step "Día y hora"). */
    staffName: string | null;
    staffPhotoUrl?: string | null;
    className?: string;
};

/** What was picked so far, so the next question never loses its context. */
export function SelectionRecap({
    wizard,
    staffName,
    staffPhotoUrl = null,
    className,
}: Props) {
    if (wizard.chosen.length === 0) {
        return null;
    }

    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-xl p-3 pr-4 skin-raised',
                className,
            )}
        >
            {staffName && staffPhotoUrl ? (
                <img
                    src={staffPhotoUrl}
                    alt=""
                    className="size-14 shrink-0 rounded-portrait object-cover"
                />
            ) : (
                <span
                    aria-hidden
                    className="flex size-14 shrink-0 items-center justify-center rounded-portrait bg-brand-soft text-brand-text"
                >
                    <Sparkles className="size-5" />
                </span>
            )}
            <p className="min-w-0 flex-1 text-sm">
                <span className="line-clamp-2 text-base font-semibold break-words">
                    {wizard.chosen.map((service) => service.name).join(' + ')}
                </span>
                {staffName && (
                    <span className="block break-words text-muted-foreground">
                        Con {staffName}
                    </span>
                )}
            </p>
            <p className="shrink-0 text-right text-base font-bold tabular-nums">
                <span className="block text-xs font-normal text-muted-foreground">
                    desde
                </span>
                {formatPrice(wizard.estimate)}
            </p>
        </div>
    );
}
