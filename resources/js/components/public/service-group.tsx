import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import { useSalonTheme } from '@/components/salon/brand-theme';
import type { ServiceOffer } from '@/lib/promo-offers';
import { CHIP_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { ListRow } from './service-row/list-row';
import { PhotoCard } from './service-row/photo-card';
import { RoundThumbRow } from './service-row/round-thumb-row';
import type { PublicServiceGroup } from './types';

/** A group longer than this shows its first few and "Ver los N" (plantillas.md §6). */
const LONG_GROUP = 8;
const SHOWN_OF_LONG = 6;

const LABEL_CLASS =
    'text-xs font-bold tracking-[.16em] text-muted-foreground uppercase';

type Props = {
    group: PublicServiceGroup;
    bookingUrl: string | null;
    /** A salon photo for the group's first card (photo-card only). */
    photoUrl: string | null;
    /** By service: its price with the best promotion that lowers it. */
    offers?: Map<number, ServiceOffer>;
};

/** One group of services in the arrangement of the template (`serviceRow`). */
export function ServiceGroup({ group, bookingUrl, photoUrl, offers }: Props) {
    const { layout } = useSalonTheme();
    const [isExpanded, setIsExpanded] = useState(false);
    const headingId = useId();
    const isLong = group.services.length > LONG_GROUP;
    const services =
        isLong && !isExpanded
            ? group.services.slice(0, SHOWN_OF_LONG)
            : group.services;

    const rows = services.map((service, index) => {
        const props = {
            service,
            bookingUrl,
            group: group.label,
            offer: offers?.get(service.id),
        };

        if (layout.serviceRow === 'photo-card') {
            return (
                <PhotoCard
                    key={service.id}
                    {...props}
                    photoUrl={index === 0 ? photoUrl : null}
                />
            );
        }

        return layout.serviceRow === 'round-thumb' ? (
            <RoundThumbRow key={service.id} {...props} />
        ) : (
            <ListRow key={service.id} {...props} />
        );
    });

    const showAll = isLong && !isExpanded && (
        <button
            type="button"
            className={cn(CHIP_ACTION, 'w-full')}
            onClick={() => setIsExpanded(true)}
        >
            Ver los {group.services.length} de {group.label}
            <ChevronDown aria-hidden />
        </button>
    );

    if (layout.serviceRow === 'list') {
        return (
            <section
                aria-labelledby={headingId}
                className="overflow-hidden rounded-xl skin-card"
            >
                <h3
                    id={headingId}
                    className={cn(LABEL_CLASS, 'px-4 pt-5 sm:px-5')}
                >
                    {group.label}
                </h3>
                <ul className="divide-y">{rows}</ul>
                {showAll && <div className="px-4 pb-4 sm:px-5">{showAll}</div>}
            </section>
        );
    }

    return (
        <section aria-labelledby={headingId} className="flex flex-col gap-3">
            <h3 id={headingId} className={LABEL_CLASS}>
                {group.label}
            </h3>
            <ul
                className={
                    layout.serviceRow === 'photo-card'
                        ? 'grid grid-cols-2 gap-3 lg:grid-cols-3'
                        : 'flex flex-col gap-3.5'
                }
            >
                {rows}
            </ul>
            {showAll}
        </section>
    );
}
