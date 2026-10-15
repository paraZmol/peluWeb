import { Link } from '@inertiajs/react';
import { ArrowRight, TimerOff } from 'lucide-react';
import { useReducedMotion } from 'motion/react';
import { useEffect, useRef } from 'react';
import { CHIP_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { PromotionCard } from './promotion-card';
import { SectionHeading } from './section-heading';
import type { PublicPromotions, PublicSalon } from './types';

type Props = {
    promotions: PublicPromotions;
    salon: Pick<PublicSalon, 'slug' | 'bookingUrl' | 'serviceGroups'>;
};

/**
 * What the salon has on offer today, above its services. Coming through a
 * promotion's own link, the page opens here: that one first and raised, or
 * the word that it is no longer on offer (over, paused or out of places)
 * and the way to book at the normal price.
 */
export function PromotionStrip({ promotions, salon }: Props) {
    const { items, shared } = promotions;
    const section = useRef<HTMLElement>(null);
    const reduceMotion = useReducedMotion();
    const featuredId = shared?.id ?? null;
    const ordered = [...items].sort(
        (a, b) => Number(b.id === featuredId) - Number(a.id === featuredId),
    );
    const hasFeatured = ordered[0]?.id === featuredId;
    const serviceNames = new Map(
        salon.serviceGroups.flatMap((group) =>
            group.services.map((service) => [service.id, service.name]),
        ),
    );
    // Two columns from `sm`: whatever would hang alone takes the whole row.
    const isWide = (index: number): boolean =>
        index === 0
            ? hasFeatured || ordered.length % 2 === 1
            : hasFeatured &&
              ordered.length % 2 === 0 &&
              index === ordered.length - 1;

    useEffect(() => {
        if (shared !== null) {
            section.current?.scrollIntoView({
                behavior: reduceMotion ? 'auto' : 'smooth',
                block: 'start',
            });
        }
        // Only on arrival.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (items.length === 0 && !shared?.hasEnded) {
        return null;
    }

    return (
        <section
            ref={section}
            id="promociones"
            aria-labelledby={
                items.length > 0 ? 'promociones-titulo' : undefined
            }
            aria-label={items.length > 0 ? undefined : 'Promociones'}
            className="flex scroll-mt-6 flex-col gap-6"
        >
            {shared?.hasEnded && (
                <div
                    role="status"
                    className="flex flex-col gap-4 rounded-xl p-5 skin-raised sm:flex-row sm:items-center"
                >
                    <span
                        aria-hidden
                        className="flex size-12 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"
                    >
                        <TimerOff className="size-6" />
                    </span>
                    <p className="min-w-0 flex-1">
                        <strong className="block text-lg">
                            Esta promoción ya no está disponible.
                        </strong>
                        <span className="text-muted-foreground">
                            Puedes reservar al precio normal.
                        </span>
                    </p>
                    {salon.bookingUrl && (
                        <Link
                            href={salon.bookingUrl}
                            className={cn(CHIP_ACTION, 'shrink-0')}
                        >
                            Reservar cita <ArrowRight aria-hidden />
                        </Link>
                    )}
                </div>
            )}

            {items.length > 0 && (
                <>
                    <SectionHeading
                        id="promociones-titulo"
                        eyebrow="Por tiempo limitado"
                        title="Promociones"
                    />
                    <ul className="grid gap-4 sm:grid-cols-2">
                        {ordered.map((promotion, index) => (
                            <PromotionCard
                                key={promotion.id}
                                promotion={promotion}
                                serviceNames={serviceNames}
                                salonSlug={salon.bookingUrl ? salon.slug : null}
                                isFeatured={promotion.id === featuredId}
                                className={cn(isWide(index) && 'sm:col-span-2')}
                            />
                        ))}
                    </ul>
                </>
            )}
        </section>
    );
}
