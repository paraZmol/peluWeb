import { FitTitle } from '../fit-title';
import { OpeningStatus } from '../opening-status';
import { SalonMark } from '../salon-mark';
import { salonKind } from './cover';
import type { HeroProps } from './cover';
import { HeroToolbar } from './hero-toolbar';

/**
 * Editorial: a 16:9 cover that melts into the page, the logo mounted on its
 * edge and the details below. Also what the photo covers fall back to when
 * a salon has no photo at all: a soft block of its color with its mark.
 */
export function StackedHero({ salon }: HeroProps) {
    const cover = salon.coverUrl;

    return (
        <header className="relative">
            <div
                className={
                    cover
                        ? 'relative aspect-video w-full overflow-hidden md:aspect-auto md:h-96'
                        : 'h-36 w-full bg-linear-to-b from-brand-soft to-band md:h-44'
                }
            >
                {cover && (
                    <>
                        <img
                            src={cover}
                            alt=""
                            fetchPriority="high"
                            className="size-full object-cover"
                        />
                        <div
                            aria-hidden
                            className="absolute inset-0 bg-linear-to-t from-background via-background/10 to-transparent"
                        />
                        {/* Keeps the glass buttons readable on a bright photo. */}
                        <div
                            aria-hidden
                            className="absolute inset-0 photo-veil-top"
                        />
                    </>
                )}
            </div>
            <HeroToolbar
                salon={salon}
                tone={cover ? 'glass' : 'chip'}
                className="absolute inset-x-0 top-0 mx-auto max-w-6xl px-4 pt-4"
            />

            <div className="relative mx-auto -mt-12 flex w-full max-w-6xl flex-col gap-4 px-4">
                <SalonMark
                    name={salon.name}
                    logoUrl={salon.logoUrl}
                    size="lg"
                    className="ring-4 ring-background"
                />
                <div className="@container lg:max-w-[calc(100%-25rem)]">
                    <FitTitle text={salon.name} />
                </div>
                <p className="text-sm font-semibold text-muted-foreground">
                    {salonKind(salon)}
                </p>
                {salon.openingHours && (
                    <OpeningStatus hours={salon.openingHours} />
                )}
                {salon.description && (
                    <p className="max-w-2xl text-base whitespace-pre-line text-muted-foreground">
                        {salon.description}
                    </p>
                )}
            </div>
        </header>
    );
}
