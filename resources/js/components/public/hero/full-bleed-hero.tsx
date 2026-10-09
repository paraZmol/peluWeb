import { FitTitle } from '../fit-title';
import { OpeningStatus } from '../opening-status';
import { SalonMark } from '../salon-mark';
import { coverPhoto, salonKind } from './cover';
import type { HeroProps } from './cover';
import { HeroToolbar } from './hero-toolbar';

/**
 * Nocturna: the photo to the edges, tall, with a strong veil; the salon's
 * name sits on it. On desktop the booking card floats on its right side
 * (BookingCard lifts itself into the cover).
 */
export function FullBleedHero({ salon }: HeroProps) {
    // SalonHero only picks this cover when there is a photo.
    const cover = coverPhoto(salon) ?? '';

    return (
        <header className="relative isolate overflow-hidden rounded-b-3xl bg-veil text-on-photo lg:mx-3">
            {/* photo-frame sets its own position: wrap it to place it. */}
            <div className="absolute inset-0 -z-10">
                <div className="photo-frame size-full">
                    <img
                        src={cover}
                        alt=""
                        fetchPriority="high"
                        className="scale-130 md:scale-100"
                    />
                </div>
            </div>
            <div aria-hidden className="absolute inset-0 -z-10 photo-veil" />
            <div
                aria-hidden
                className="absolute inset-0 -z-10 photo-veil-top"
            />
            <div
                aria-hidden
                className="absolute inset-0 -z-10 hidden bg-linear-to-r from-veil/85 via-veil/35 to-transparent lg:block"
            />

            <div className="mx-auto flex min-h-[78svh] w-full max-w-6xl flex-col px-4 pt-4 pb-8 lg:min-h-160 lg:pb-20">
                <HeroToolbar salon={salon} tone="glass" />

                <div className="mt-auto flex max-w-2xl flex-col gap-4 pt-16 lg:max-w-[calc(100%-25rem)]">
                    <div className="flex items-center gap-3">
                        <SalonMark name={salon.name} logoUrl={salon.logoUrl} />
                        <p className="text-sm leading-snug font-semibold text-on-photo/90">
                            {salonKind(salon)}
                        </p>
                    </div>
                    <div className="@container">
                        <FitTitle text={salon.name} />
                    </div>
                    {salon.description && (
                        <p className="line-clamp-3 max-w-xl text-base whitespace-pre-line text-on-photo/85 lg:text-lg">
                            {salon.description}
                        </p>
                    )}
                    {salon.openingHours && (
                        <OpeningStatus
                            hours={salon.openingHours}
                            tone="glass"
                        />
                    )}
                </div>
            </div>
        </header>
    );
}
