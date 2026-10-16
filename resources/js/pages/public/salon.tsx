import { Head } from '@inertiajs/react';
import { BookingCard } from '@/components/public/booking-card';
import { OpeningHours } from '@/components/public/opening-hours';
import { PhotoGallery } from '@/components/public/photo-gallery';
import { PreviewBanner } from '@/components/public/preview-banner';
import { PromotionStrip } from '@/components/public/promotion-strip';
import { Reveal } from '@/components/public/reveal';
import { InstallApp } from '@/components/install-app';
import { ReviewsSection } from '@/components/public/reviews-section';
import { SalonComplaintsLine } from '@/components/public/salon-complaints-line';
import { SalonFooter } from '@/components/public/salon-footer';
import { SalonHero } from '@/components/public/salon-hero';
import { SalonLocation } from '@/components/public/salon-location';
import { ServiceList } from '@/components/public/service-list';
import { StickyActions } from '@/components/public/sticky-actions';
import { TeamSection } from '@/components/public/team-section';
import type { PublicPromotions, PublicSalon } from '@/components/public/types';
import type { PublicReviews } from '@/components/reviews/types';
import { BrandTheme } from '@/components/salon/brand-theme';
import { useFramedPreview } from '@/hooks/use-framed-preview';
import { usePageTemplate } from '@/hooks/use-page-template';
import { bestOffers } from '@/lib/promo-offers';
import { ThemeBackdrop } from '@/components/salon/theme-backdrop';

type Props = {
    salon: PublicSalon;
    /** Null unless the salon shows its reviews: then nothing is rendered. */
    reviews: PublicReviews | null;
    /** What it runs today, and the one the visitor's link points at. */
    promotions?: PublicPromotions;
};

const NO_PROMOTIONS: PublicPromotions = { items: [], shared: null };

/**
 * The salon's public page in its own template (ui-system §4). Phones: one
 * column and the dock. Desktop: services, team, reviews and gallery on the left; the
 * booking card, hours and map on the right.
 */
export default function Salon({
    salon,
    reviews,
    promotions = NO_PROMOTIONS,
}: Props) {
    const hasLocation = salon.location !== null || salon.address !== null;
    const isFramed = useFramedPreview(salon.isPreview);

    usePageTemplate(salon);

    return (
        <BrandTheme
            brand={salon.brand}
            theme={salon.theme}
            className="min-h-svh overflow-x-clip bg-background text-foreground"
        >
            <Head title={salon.name} />
            <ThemeBackdrop />
            {salon.isPreview && (
                <PreviewBanner
                    theme={salon.theme}
                    usesSamplePhotos={salon.usesSamplePhotos}
                    isFramed={isFramed}
                />
            )}

            <Reveal order={0}>
                <SalonHero salon={salon} />
            </Reveal>

            <div className="mx-auto grid w-full max-w-6xl grid-cols-1 gap-x-12 gap-y-14 px-4 pt-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:grid-rows-[auto_1fr] lg:items-start lg:pt-14">
                <Reveal order={1} className="lg:col-start-2 lg:row-start-1">
                    <BookingCard salon={salon} />
                </Reveal>

                <main className="flex min-w-0 flex-col gap-14 lg:col-start-1 lg:row-span-2 lg:row-start-1 lg:gap-16">
                    <Reveal order={2} className="empty:hidden">
                        <PromotionStrip promotions={promotions} salon={salon} />
                    </Reveal>
                    <Reveal order={2}>
                        <ServiceList
                            groups={salon.serviceGroups}
                            bookingUrl={salon.bookingUrl}
                            whatsappUrl={salon.whatsappUrl}
                            photos={salon.photos}
                            offers={bestOffers(promotions.items)}
                        />
                    </Reveal>
                    {salon.team.length > 0 && (
                        <Reveal order={3}>
                            <TeamSection team={salon.team} />
                        </Reveal>
                    )}
                    {reviews && (
                        <Reveal order={4}>
                            <ReviewsSection
                                reviews={reviews}
                                salonName={salon.name}
                            />
                        </Reveal>
                    )}
                    {salon.photos.length > 0 && (
                        <Reveal order={5}>
                            <PhotoGallery
                                salonName={salon.name}
                                photos={salon.photos}
                            />
                        </Reveal>
                    )}
                </main>

                <aside className="flex min-w-0 flex-col gap-14 lg:col-start-2 lg:row-start-2 lg:gap-10">
                    {salon.openingHours && (
                        <Reveal order={6}>
                            <OpeningHours hours={salon.openingHours} />
                        </Reveal>
                    )}
                    {hasLocation && (
                        <Reveal order={7}>
                            <SalonLocation
                                address={salon.address}
                                location={salon.location}
                            />
                        </Reveal>
                    )}
                    <InstallApp name={salon.name} />
                </aside>
            </div>

            <SalonComplaintsLine
                salonName={salon.name}
                address={salon.address}
                book={salon.complaintsBook}
            />
            <SalonFooter />
            {/* Outside every Reveal: a transformed ancestor breaks `fixed`. */}
            <StickyActions
                bookingUrl={salon.bookingUrl}
                isPaused={salon.bookingsPaused}
                whatsappUrl={salon.whatsappUrl}
                directionsUrl={salon.location?.directionsUrl ?? null}
            />
        </BrandTheme>
    );
}
