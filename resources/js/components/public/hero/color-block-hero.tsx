import { Link } from '@inertiajs/react';
import { ArrowRight, MapPin, MessageCircle, Navigation } from 'lucide-react';
import { ON_BRAND_ACTION } from '@/lib/skin-actions';
import { FitTitle } from '../fit-title';
import { OpeningStatus } from '../opening-status';
import { salonKind } from './cover';
import type { HeroProps } from './cover';
import { HeroCollage } from './hero-collage';
import { HeroToolbar } from './hero-toolbar';
import { WaveEdge } from './wave-edge';

/**
 * Vibrante: the brand color as a full block with the name, the status as a
 * sticker and the ways to reach the salon; a collage of photos with shapes
 * on the side, and a wavy edge into the page.
 */
export function ColorBlockHero({ salon }: HeroProps) {
    const photos = [
        ...new Set(
            [salon.coverUrl, ...salon.photos.map((photo) => photo.url)].filter(
                (url): url is string => url !== null,
            ),
        ),
    ];
    const directionsHref =
        salon.location?.directionsUrl ?? (salon.address ? '#ubicacion' : null);

    return (
        <header className="relative bg-primary pb-14 text-primary-foreground lg:pb-24">
            <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 pt-4 lg:grid-cols-[1.05fr_.95fr] lg:items-end lg:gap-14 lg:pt-6">
                <HeroToolbar
                    salon={salon}
                    tone="on-brand"
                    showMark
                    className="lg:col-span-2"
                />

                <div className="flex flex-col gap-4">
                    {salon.openingHours && (
                        <OpeningStatus
                            hours={salon.openingHours}
                            tone="sticker"
                        />
                    )}
                    <div className="@container">
                        <FitTitle text={salon.name} />
                    </div>
                    <p className="flex items-center gap-2 text-base font-medium lg:text-lg">
                        <MapPin className="size-5 shrink-0" aria-hidden />
                        {salonKind(salon)}
                    </p>
                    {salon.description && (
                        <p className="max-w-xl text-base whitespace-pre-line text-primary-foreground/85">
                            {salon.description}
                        </p>
                    )}
                    <div className="mt-2 flex flex-wrap gap-2.5">
                        {salon.bookingUrl && (
                            <Link
                                href={salon.bookingUrl}
                                className="hidden h-12 items-center gap-2 rounded-full px-5 font-semibold skin-raised transition-transform active:translate-x-0.5 active:translate-y-0.5 lg:inline-flex"
                            >
                                Reservar cita{' '}
                                <ArrowRight className="size-5" aria-hidden />
                            </Link>
                        )}
                        {salon.whatsappUrl && (
                            <a
                                href={salon.whatsappUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={ON_BRAND_ACTION}
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
                                className={ON_BRAND_ACTION}
                            >
                                <Navigation aria-hidden /> Cómo llegar
                            </a>
                        )}
                    </div>
                </div>

                <HeroCollage photos={photos} />
            </div>
            <WaveEdge />
        </header>
    );
}
