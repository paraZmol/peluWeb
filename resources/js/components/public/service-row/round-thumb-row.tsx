import { ArrowRight, Clock } from 'lucide-react';
import { formatMinutes } from '@/lib/format';
import { PromoTag } from '../promo-tag';
import { CategoryIcon } from './category-icon';
import { RowPrice } from './row-price';
import type { ServiceRowProps } from './row-props';
import { ServiceBookLink } from './service-book-link';
import { ServiceExtras } from './service-extras';

/**
 * Vibrante: each service is its own card, with a round thumbnail, the
 * duration, the price and a soft "Reservar" pill. The pill's link stretches
 * over the whole card (one big target, one tab stop); the options toggle
 * stays above it.
 */
export function RoundThumbRow({
    service,
    bookingUrl,
    group,
    offer,
}: ServiceRowProps) {
    return (
        <li className="relative flex flex-col gap-2 rounded-xl p-3 pr-4 skin-card">
            <div className="flex items-center gap-3.5">
                <CategoryIcon group={group} />
                <div className="min-w-0 flex-1">
                    <h4 className="font-semibold break-words">
                        {service.name}
                    </h4>
                    <p className="mt-0.5 flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground tabular-nums">
                        <Clock className="size-4" aria-hidden />
                        {formatMinutes(service.minutes)}
                        {offer && <PromoTag className="ml-1" />}
                    </p>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1.5">
                    <p className="font-bold whitespace-nowrap tabular-nums">
                        <RowPrice
                            service={service}
                            offer={offer}
                            fromClassName="mr-1 text-xs font-medium text-muted-foreground"
                        />
                    </p>
                    <ServiceBookLink
                        service={service}
                        bookingUrl={bookingUrl}
                        className="inline-flex h-11 items-center gap-1 rounded-full bg-brand-soft px-4 text-sm font-semibold text-brand-text outline-none after:absolute after:inset-0 after:rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring"
                    >
                        Reservar <ArrowRight className="size-4" aria-hidden />
                    </ServiceBookLink>
                </div>
            </div>
            {(service.description ||
                service.requiresEvaluation ||
                service.hasVariants) && (
                <div className="pl-15.5">
                    {service.description && (
                        <p className="line-clamp-2 text-sm text-muted-foreground">
                            {service.description}
                        </p>
                    )}
                    <div className="relative z-10 w-fit max-w-full empty:hidden">
                        <ServiceExtras service={service} />
                    </div>
                </div>
            )}
        </li>
    );
}
