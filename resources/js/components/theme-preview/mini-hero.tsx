import type { SalonThemeLayout } from '@/components/salon/salon-themes';
import { MiniCardOverCover, MiniColorBlock } from './mini-hero-blocks';
import { MiniCover, MiniMonogram, MiniName, MiniStatus } from './mini-parts';
import type { MiniSalon } from './types';

type Props = { salon: MiniSalon };

/** Photo to the edge, everything on the veil (Nocturna). */
function MiniFullBleed({ salon }: Props) {
    return (
        <MiniCover src={salon.coverUrl} className="h-[98cqi] rounded-b-[6cqi]">
            <div className="absolute inset-0 photo-veil" />
            <div className="absolute inset-0 photo-veil-top" />
            <div className="absolute inset-x-0 bottom-0 z-10 flex flex-col gap-[2.4cqi] p-[5cqi] text-on-photo">
                <div className="flex items-center gap-[2.4cqi]">
                    <MiniMonogram
                        salon={salon}
                        className="size-[10cqi] rounded-[2.6cqi] text-[3.6cqi]"
                    />
                    <span className="truncate text-[2.8cqi] font-semibold tracking-[.12em] text-on-photo/80 uppercase">
                        {salon.subtitle}
                    </span>
                </div>
                <MiniName name={salon.name} fitSize="15cqi" />
                <MiniStatus onPhoto />
            </div>
        </MiniCover>
    );
}

/** Cover 16:9 that melts into the page, logo mounted, name below (Editorial). */
function MiniStacked({ salon }: Props) {
    return (
        <div className="relative">
            <MiniCover src={salon.coverUrl} className="aspect-video">
                <div className="absolute inset-0 bg-linear-to-t from-background to-transparent to-50%" />
            </MiniCover>
            <div className="relative -mt-[9cqi] flex flex-col gap-[2cqi] px-[5cqi]">
                <MiniMonogram
                    salon={salon}
                    className="size-[15cqi] rounded-[3.5cqi] text-[5cqi] outline-[1cqi] outline-background"
                />
                <MiniName name={salon.name} fitSize="9cqi" />
                <p className="truncate text-[3.2cqi] text-muted-foreground">
                    {salon.subtitle}
                </p>
                <MiniStatus />
            </div>
        </div>
    );
}

/** The cover of the miniature, in the arrangement of its template. */
export function MiniHero({
    salon,
    variant,
}: Props & { variant: SalonThemeLayout['hero'] }) {
    switch (variant) {
        case 'full-bleed':
            return <MiniFullBleed salon={salon} />;
        case 'card-over-cover':
            return <MiniCardOverCover salon={salon} />;
        case 'color-block':
            return <MiniColorBlock salon={salon} />;
        case 'stacked':
            return <MiniStacked salon={salon} />;
    }
}
