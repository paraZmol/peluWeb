import { ArrowRight, Clock, Sparkles } from 'lucide-react';
import type { SalonThemeLayout } from '@/components/salon/salon-themes';
import { formatMinutes, formatPrice } from '@/lib/format';
import { MiniCover } from './mini-parts';
import type { MiniService } from './types';

type Props = {
    services: MiniService[];
    /** A photo for the featured card of `photo-card` (none: a text card). */
    featuredPhoto: string | null;
};

const LABEL_CLASS =
    'text-[2.8cqi] font-bold tracking-[.16em] text-brand-text uppercase';

/** Rows inside one card, round arrow to book (Nocturna, Editorial). */
function MiniList({ services }: Props) {
    return (
        <>
            <p className={LABEL_CLASS}>Servicios</p>
            <div className="divide-y divide-border rounded-[4cqi] skin-card">
                {services.map((service) => (
                    <div
                        key={service.name}
                        className="flex items-center gap-[3cqi] px-[4cqi] py-[3cqi]"
                    >
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-[3.6cqi] font-semibold">
                                {service.name}
                            </p>
                            <p className="text-[3cqi] text-muted-foreground tabular-nums">
                                {formatMinutes(service.minutes)}
                            </p>
                        </div>
                        <span className="text-[3.6cqi] font-extrabold tabular-nums">
                            {formatPrice(service.price)}
                        </span>
                        <span className="flex size-[8cqi] shrink-0 items-center justify-center rounded-full skin-chip">
                            <ArrowRight className="size-[4cqi]" />
                        </span>
                    </div>
                ))}
            </div>
        </>
    );
}

/** A featured photo card with a price sticker, then text cards (Luminosa). */
function MiniPhotoCards({ services, featuredPhoto }: Props) {
    const [featured, ...rest] = services;

    return (
        <>
            <p className="display-type text-[7cqi]">Servicios</p>
            <MiniCover src={featuredPhoto} className="h-[42cqi] rounded-[5cqi]">
                <div className="absolute inset-0 photo-veil" />
                <div className="absolute inset-x-0 bottom-0 z-10 p-[4cqi] text-on-photo">
                    <p className="display-type text-[5.4cqi]">
                        {featured.name}
                    </p>
                    <p className="text-[3cqi] text-on-photo/85 tabular-nums">
                        {formatMinutes(featured.minutes)}
                    </p>
                </div>
                <span className="absolute top-[3cqi] right-[3cqi] z-10 rotate-[-4deg] rounded-full px-[2.6cqi] py-[1cqi] text-[3.2cqi] font-extrabold skin-raised tabular-nums">
                    {formatPrice(featured.price)}
                </span>
            </MiniCover>
            <div className="grid grid-cols-2 gap-[3cqi]">
                {rest.map((service) => (
                    <div
                        key={service.name}
                        className="flex flex-col gap-[1.4cqi] rounded-[4cqi] p-[3.4cqi] skin-card"
                    >
                        <p className="line-clamp-2 display-type text-[4.4cqi]">
                            {service.name}
                        </p>
                        <span className="w-fit rounded-full bg-brand-soft px-[2.4cqi] py-[0.8cqi] text-[3cqi] font-bold text-brand-text tabular-nums">
                            {formatPrice(service.price)}
                        </span>
                    </div>
                ))}
            </div>
        </>
    );
}

/** One card per service with a round icon and a soft "Reservar" (Vibrante). */
function MiniRoundThumbs({ services }: Props) {
    return (
        <>
            <p className="display-type text-[7cqi]">Servicios</p>
            {services.map((service) => (
                <div
                    key={service.name}
                    className="flex items-center gap-[3cqi] rounded-[4cqi] p-[3cqi] skin-card"
                >
                    <span className="flex size-[10cqi] shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-text">
                        <Sparkles className="size-[4.6cqi]" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-[3.6cqi] font-bold">
                            {service.name}
                        </p>
                        <p className="flex items-center gap-[1cqi] text-[3cqi] text-muted-foreground tabular-nums">
                            <Clock className="size-[3cqi]" />
                            {formatMinutes(service.minutes)}
                        </p>
                    </div>
                    <div className="flex flex-col items-end gap-[1cqi]">
                        <span className="text-[3.4cqi] font-extrabold tabular-nums">
                            {formatPrice(service.price)}
                        </span>
                        <span className="rounded-full bg-brand-soft px-[2.4cqi] py-[0.6cqi] text-[2.8cqi] font-bold text-brand-text">
                            Reservar
                        </span>
                    </div>
                </div>
            ))}
        </>
    );
}

/** The service list of the miniature, in the arrangement of its template. */
export function MiniServices({
    variant,
    ...props
}: Props & { variant: SalonThemeLayout['serviceRow'] }) {
    if (props.services.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-[3cqi] px-[5cqi] pt-[6cqi] pb-[22cqi]">
            {variant === 'list' && <MiniList {...props} />}
            {variant === 'photo-card' && <MiniPhotoCards {...props} />}
            {variant === 'round-thumb' && <MiniRoundThumbs {...props} />}
        </div>
    );
}
