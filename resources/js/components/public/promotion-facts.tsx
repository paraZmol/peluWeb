import { CalendarDays, Clock, Ticket, UserRound } from 'lucide-react';
import { staffNamesOf } from '@/lib/promo-staff';
import { shortWhen } from '@/lib/promo-when';
import { list, quotaLabel } from '@/lib/promotions';
import type { PublicPromotion } from './types';

type Props = {
    promotion: PublicPromotion;
    /** Its days and hours are already said by its title. */
    hidesWhen: boolean;
};

/** With whom and when it holds, how long it runs and how many it takes. */
export function PromotionFacts({ promotion, hidesWhen }: Props) {
    const quota = quotaLabel(promotion.quota);
    const staffNames = staffNamesOf(promotion);
    const when = shortWhen(promotion.when);

    return (
        <ul className="flex flex-col gap-1.5 text-sm text-muted-foreground">
            {staffNames.length > 0 && (
                <li className="flex items-start gap-2 font-semibold text-foreground">
                    <UserRound
                        className="mt-0.5 size-4 shrink-0 text-brand-text"
                        aria-hidden
                    />
                    Con {list(staffNames)}
                </li>
            )}
            {when && !hidesWhen && (
                <li className="flex items-start gap-2">
                    <Clock className="mt-0.5 size-4 shrink-0" aria-hidden />
                    <span className="first-letter:uppercase">{when}</span>
                </li>
            )}
            <li className="flex items-start gap-2">
                <CalendarDays className="mt-0.5 size-4 shrink-0" aria-hidden />
                Válido {promotion.period}
            </li>
            {quota && (
                <li className="flex items-start gap-2 tabular-nums">
                    <Ticket className="mt-0.5 size-4 shrink-0" aria-hidden />
                    {quota}
                </li>
            )}
        </ul>
    );
}
