import { FitTitle } from '../fit-title';
import { OpeningStatus } from '../opening-status';
import { coverPhoto, salonKind } from './cover';
import type { HeroProps } from './cover';
import { HeroToolbar } from './hero-toolbar';

/**
 * Luminosa: a big photo with the name on it; the description and the ways
 * to reach the salon live in a floating card that mounts on its lower edge
 * (BookingCard). On desktop the photo is a rounded panel with a margin.
 */
export function CardOverCoverHero({ salon }: HeroProps) {
    // SalonHero only picks this cover when there is a photo.
    const cover = coverPhoto(salon) ?? '';

    return (
        <header className="lg:mx-auto lg:max-w-6xl lg:px-4 lg:pt-6">
            <div className="relative isolate h-117 overflow-hidden text-on-photo lg:h-140 lg:rounded-3xl lg:shadow-raised">
                {/* photo-frame sets its own position: wrap it to place it. */}
                <div className="absolute inset-0 -z-10">
                    <div className="photo-frame size-full">
                        <img src={cover} alt="" fetchPriority="high" />
                    </div>
                </div>
                <div
                    aria-hidden
                    className="absolute inset-0 -z-10 photo-veil"
                />
                <div
                    aria-hidden
                    className="absolute inset-0 -z-10 photo-veil-top"
                />

                <HeroToolbar
                    salon={salon}
                    tone="glass"
                    showMark
                    className="absolute inset-x-0 top-0 px-4 pt-4 lg:px-6 lg:pt-6"
                />

                <div className="absolute inset-x-0 bottom-0 flex flex-col gap-3 px-4 pb-18 lg:px-10 lg:pr-[26rem] lg:pb-12">
                    {salon.openingHours && (
                        <OpeningStatus
                            hours={salon.openingHours}
                            tone="glass"
                        />
                    )}
                    <div className="@container">
                        <FitTitle text={salon.name} />
                    </div>
                    <p className="text-base font-medium text-on-photo/90">
                        {salonKind(salon)}
                    </p>
                </div>
            </div>
        </header>
    );
}
