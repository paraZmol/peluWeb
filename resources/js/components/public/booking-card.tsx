import { Link } from '@inertiajs/react';
import { ArrowRight, MessageCircle, Navigation } from 'lucide-react';
import { useSalonTheme } from '@/components/salon/brand-theme';
import { CHIP_ACTION, PRIMARY_ACTION, SOFT_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { BookingCardHeading } from './booking-card-heading';
import { effectiveHero } from './hero/cover';
import { QuickFacts, salonFacts } from './quick-facts';
import type { PublicSalon } from './types';

/**
 * How the card sits for each cover: framed always and mounted on the photo
 * (card-over-cover), or only from `lg`, lifted into a photo cover or at the
 * top of the side column. On phones the other covers keep just the facts.
 */
const PLACEMENT = {
    'full-bleed': { framed: false, lift: 'lg:-mt-72' },
    'card-over-cover': { framed: true, lift: '-mt-20 lg:-mt-48' },
    'color-block': { framed: false, lift: '' },
    stacked: { framed: false, lift: '' },
};

type Props = { salon: PublicSalon; className?: string };

/** Booking at hand: in the cover on desktop (the dock does it on phones). */
export function BookingCard({ salon, className }: Props) {
    const { layout } = useSalonTheme();
    const { framed, lift } = PLACEMENT[effectiveHero(layout.hero, salon)];
    const facts = salonFacts(salon);
    const directionsHref =
        salon.location?.directionsUrl ?? (salon.address ? '#ubicacion' : null);

    return (
        <div
            className={cn(
                'relative z-10',
                lift,
                !framed &&
                    facts.length === 0 &&
                    !salon.bookingsPaused &&
                    'max-lg:hidden',
                className,
            )}
        >
            <div
                aria-hidden
                className="absolute inset-x-6 top-10 -bottom-6 -z-10 brand-halo"
            />
            <section
                aria-labelledby="booking-card-title"
                className={cn(
                    'flex flex-col gap-4',
                    framed
                        ? 'rounded-xl p-5 skin-raised lg:p-6'
                        : 'lg:rounded-xl lg:p-6 lg:skin-raised',
                )}
            >
                <BookingCardHeading
                    isPaused={salon.bookingsPaused}
                    hasWhatsapp={salon.whatsappUrl !== null}
                    framed={framed}
                />

                {/* Paused, WhatsApp is the way to a visit: the main action, framed or not. */}
                {salon.bookingsPaused && salon.whatsappUrl && (
                    <a
                        href={salon.whatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={cn(PRIMARY_ACTION, 'max-lg:hidden')}
                    >
                        <MessageCircle aria-hidden /> Pedir cita por WhatsApp
                    </a>
                )}

                {framed && salon.description && (
                    <p className="text-base whitespace-pre-line text-muted-foreground">
                        {salon.description}
                    </p>
                )}

                <div className="hidden flex-col gap-2 lg:flex">
                    {salon.bookingUrl && (
                        <Link
                            href={salon.bookingUrl}
                            className={PRIMARY_ACTION}
                        >
                            Reservar cita <ArrowRight aria-hidden />
                        </Link>
                    )}
                    {!framed && !salon.bookingsPaused && salon.whatsappUrl && (
                        <a
                            href={salon.whatsappUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={
                                salon.bookingUrl ? CHIP_ACTION : PRIMARY_ACTION
                            }
                        >
                            <MessageCircle aria-hidden /> Escribir por WhatsApp
                        </a>
                    )}
                </div>

                {framed && (salon.whatsappUrl || directionsHref) && (
                    <div className="grid grid-cols-2 gap-2">
                        {salon.whatsappUrl && (
                            <a
                                href={salon.whatsappUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={SOFT_ACTION}
                            >
                                <MessageCircle aria-hidden /> WhatsApp
                            </a>
                        )}
                        {directionsHref && (
                            <a
                                href={directionsHref}
                                {...(salon.location && {
                                    target: '_blank',
                                    rel: 'noopener noreferrer',
                                })}
                                className={SOFT_ACTION}
                            >
                                <Navigation aria-hidden /> Cómo llegar
                            </a>
                        )}
                    </div>
                )}

                <QuickFacts facts={facts} chipsOnMobile={!framed} />
            </section>
        </div>
    );
}
