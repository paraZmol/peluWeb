import { ArrowRight, Clock } from 'lucide-react';
import { formatMinutes, formatPrice } from '@/lib/format';
import { SOFT_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import type { PublicService } from '../types';
import type { ServiceRowProps } from './row-props';
import { ServiceBookLink } from './service-book-link';
import { ServiceExtras } from './service-extras';

function PriceSticker({ service }: { service: PublicService }) {
    return (
        <p className="w-fit -rotate-4 rounded-full bg-card px-3 py-1 text-sm leading-tight font-bold text-brand-text tabular-nums shadow-card">
            {service.hasVariants && (
                <span className="block text-xs font-medium text-muted-foreground">
                    desde
                </span>
            )}
            {formatPrice(service.priceFrom)}
        </p>
    );
}

type Props = ServiceRowProps & {
    /** A photo of the salon for the group's first card; null for a text card. */
    photoUrl: string | null;
};

/**
 * Luminosa: services as cards with a price sticker. Services have no photo
 * of their own yet, so only the first card of a group borrows one from the
 * gallery (wide, with a veil); the rest are text cards (plantillas.md §6).
 */
export function PhotoCard({ service, bookingUrl, photoUrl }: Props) {
    if (!photoUrl) {
        return (
            <li className="flex flex-col gap-3 rounded-2xl p-4 skin-card">
                <PriceSticker service={service} />
                <h4 className="display-type text-xl lg:text-2xl">
                    {service.name}
                </h4>
                <p className="flex items-center gap-1.5 text-sm text-muted-foreground tabular-nums">
                    <Clock className="size-4" aria-hidden />
                    {formatMinutes(service.minutes)}
                </p>
                <ServiceExtras service={service} />
                <ServiceBookLink
                    service={service}
                    bookingUrl={bookingUrl}
                    className={cn(SOFT_ACTION, 'mt-auto w-fit')}
                >
                    Reservar
                </ServiceBookLink>
            </li>
        );
    }

    return (
        <li className="col-span-2 flex flex-col gap-2 rounded-2xl p-1.5 skin-card">
            <div className="relative isolate flex aspect-16/10 flex-col justify-between overflow-hidden rounded-xl p-3 text-on-photo sm:p-4">
                <img
                    src={photoUrl}
                    alt=""
                    loading="lazy"
                    className="absolute inset-0 -z-10 size-full object-cover"
                />
                <div
                    aria-hidden
                    className="absolute inset-0 -z-10 photo-veil"
                />
                <div className="flex items-start justify-between gap-2">
                    <PriceSticker service={service} />
                    <p className="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold skin-glass tabular-nums">
                        <Clock className="size-3.5" aria-hidden />
                        {formatMinutes(service.minutes)}
                    </p>
                </div>
                <div className="flex items-end justify-between gap-3">
                    <div className="min-w-0">
                        <h4 className="display-type text-title-3">
                            {service.name}
                        </h4>
                        {service.description && (
                            <p className="mt-1 line-clamp-2 text-sm text-on-photo/85">
                                {service.description}
                            </p>
                        )}
                    </div>
                    <ServiceBookLink
                        service={service}
                        bookingUrl={bookingUrl}
                        className="inline-flex h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-sm font-semibold skin-glass"
                    >
                        Reservar <ArrowRight className="size-4" aria-hidden />
                    </ServiceBookLink>
                </div>
            </div>
            <div className="px-2.5 empty:hidden">
                <ServiceExtras service={service} />
            </div>
        </li>
    );
}
