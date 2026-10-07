import { Head } from '@inertiajs/react';
import { OpeningHours } from '@/components/public/opening-hours';
import { PhotoGallery } from '@/components/public/photo-gallery';
import { Reveal } from '@/components/public/reveal';
import { SalonHero } from '@/components/public/salon-hero';
import { SalonLocation } from '@/components/public/salon-location';
import { ServiceList } from '@/components/public/service-list';
import { StickyActions } from '@/components/public/sticky-actions';
import type { PublicSalon } from '@/components/public/types';
import { BrandTheme } from '@/components/salon/brand-theme';

type Props = {
    salon: PublicSalon;
};

const SECTION_CLASS = 'mx-auto w-full max-w-3xl px-4';

export default function Salon({ salon }: Props) {
    const hasPhotos = salon.photos.length > 0;
    const hasLocation = salon.location !== null || salon.address !== null;

    return (
        <BrandTheme
            brand={salon.brand}
            className="min-h-svh overflow-x-clip bg-background text-foreground"
        >
            <Head title={salon.name} />

            <main className="flex flex-col gap-10 pb-8">
                <Reveal order={0}>
                    <SalonHero salon={salon} />
                </Reveal>

                {/* Not inside Reveal: a transformed ancestor would break `fixed` on mobile */}
                <StickyActions
                    salonName={salon.name}
                    bookingUrl={salon.bookingUrl}
                    whatsappUrl={salon.whatsappUrl}
                    instagramUrl={salon.instagramUrl}
                    directionsUrl={salon.location?.directionsUrl ?? null}
                />

                <Reveal order={2} className={SECTION_CLASS}>
                    <ServiceList
                        groups={salon.serviceGroups}
                        bookingUrl={salon.bookingUrl}
                        whatsappUrl={salon.whatsappUrl}
                    />
                </Reveal>

                {hasPhotos && (
                    <Reveal order={3} className={SECTION_CLASS}>
                        <PhotoGallery
                            salonName={salon.name}
                            photos={salon.photos}
                        />
                    </Reveal>
                )}

                {salon.openingHours && (
                    <Reveal order={4} className={SECTION_CLASS}>
                        <OpeningHours hours={salon.openingHours} />
                    </Reveal>
                )}

                {hasLocation && (
                    <Reveal order={5} className={SECTION_CLASS}>
                        <SalonLocation
                            address={salon.address}
                            location={salon.location}
                        />
                    </Reveal>
                )}
            </main>

            {/* Bottom padding on mobile leaves room for the fixed action bar */}
            <footer className="pt-4 pb-[calc(6rem+env(safe-area-inset-bottom))] text-center text-sm text-muted-foreground md:pb-8">
                Hecho con peluWeb
            </footer>
        </BrandTheme>
    );
}
