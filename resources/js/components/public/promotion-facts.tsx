import { CalendarDays, Clock, Ticket } from 'lucide-react';
import { quotaLabel } from '@/lib/promotions';
import type { PublicPromotion } from './types';

type Props = {
    promotion: PublicPromotion;
    /** Its days and hours are already said by its title. */
    hidesWhen: boolean;
};

/** When it holds, how long it runs and how many it takes. */
export function PromotionFacts({ promotion, hidesWhen }: Props) {
    const quota = quotaLabel(promotion.quota);

    return (
        <ul className="flex flex-col gap-1.5 text-sm text-muted-foreground">
            {promotion.when && !hidesWhen && (
                <li className="flex items-start gap-2">
                    <Clock className="mt-0.5 size-4 shrink-0" aria-hidden />
                    <span className="first-letter:uppercase">
                        {promotion.when}
                    </span>
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
