import { ChevronRight } from 'lucide-react';
import { formatMinutes, formatPrice } from '@/lib/format';
import { CHIP_ICON } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import type { ServiceRowProps } from './row-props';
import { ServiceBookLink } from './service-book-link';
import { ServiceExtras } from './service-extras';

/**
 * Nocturna and Editorial: one row of the group's card. Name, price in bold
 * and duration; a round "Reservar" button at the thumb's side.
 */
export function ListRow({ service, bookingUrl }: ServiceRowProps) {
    return (
        <li className="flex items-start gap-3 px-4 py-4 sm:px-5">
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <h4 className="font-semibold break-words">{service.name}</h4>
                <p className="flex flex-wrap items-baseline gap-x-2 text-sm tabular-nums">
                    <span className="text-base font-extrabold">
                        {service.hasVariants && (
                            <span className="mr-1 text-sm font-normal text-muted-foreground">
                                desde
                            </span>
                        )}
                        {formatPrice(service.priceFrom)}
                    </span>
                    <span aria-hidden className="text-muted-foreground">
                        ·
                    </span>
                    <span className="text-muted-foreground">
                        {formatMinutes(service.minutes)}
                    </span>
                </p>
                {service.description && (
                    <p className="line-clamp-2 text-sm text-muted-foreground">
                        {service.description}
                    </p>
                )}
                <ServiceExtras service={service} />
            </div>
            <ServiceBookLink
                service={service}
                bookingUrl={bookingUrl}
                className={cn(CHIP_ICON, 'mt-1 size-11')}
            >
                <ChevronRight className="text-brand-text" aria-hidden />
            </ServiceBookLink>
        </li>
    );
}
