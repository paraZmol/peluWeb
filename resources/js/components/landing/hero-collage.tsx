import { BadgeCheck, CalendarCheck } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { cn } from '@/lib/utils';
import { HERO_CARDS, HERO_PHOTOS } from './content';

const EASE_OUT = [0.22, 1, 0.36, 1] as const;

type LabeledPhoto = { src: string; alt: string; label: string };

/** A real photo with a glass label for the kind of business it shows. */
function CollagePhoto({
    photo,
    className,
    isEager = false,
}: {
    photo: LabeledPhoto;
    className?: string;
    isEager?: boolean;
}) {
    return (
        <figure className={cn('photo-frame', className)}>
            <img
                src={photo.src}
                alt={photo.alt}
                loading={isEager ? 'eager' : 'lazy'}
                className="absolute inset-0"
            />
            <div aria-hidden className="absolute inset-0 photo-veil" />
            <figcaption className="absolute bottom-3 left-3 z-10 rounded-full px-3 py-1 text-xs font-semibold skin-glass">
                {photo.label}
            </figcaption>
        </figure>
    );
}

/**
 * Hero art in three planes: a soft brand glow, three photos (salon,
 * barbershop, nails: every kind of business at once) and two floating cards
 * with what the owner gets (a booking, a deposit).
 */
export function HeroCollage() {
    const reduceMotion = useReducedMotion();
    const float = (delay: number, offsetY: number) => ({
        initial: { opacity: 0, y: reduceMotion ? 0 : offsetY },
        animate: { opacity: 1, y: 0 },
        transition: {
            duration: reduceMotion ? 0.15 : 0.5,
            delay: reduceMotion ? 0 : delay,
            ease: EASE_OUT,
        },
    });

    return (
        <div className="relative mx-auto w-full max-w-[34rem] pt-6 pb-8 sm:px-6 lg:px-0">
            <div className="grid h-[22rem] grid-cols-[1.35fr_1fr] grid-rows-2 gap-3 sm:h-[30rem] sm:gap-4 lg:h-[34rem]">
                <CollagePhoto
                    photo={HERO_PHOTOS.main}
                    className="row-span-2 rounded-3xl shadow-raised"
                    isEager
                />
                {HERO_PHOTOS.side.map((photo) => (
                    <CollagePhoto
                        key={photo.src}
                        photo={photo}
                        className="rounded-2xl shadow-card"
                        isEager
                    />
                ))}
            </div>

            <motion.div
                {...float(0.35, 12)}
                className="absolute top-0 -left-1 flex max-w-[16rem] items-start gap-3 rounded-2xl p-3 pr-4 skin-raised sm:-left-2 lg:-left-10"
            >
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand-text">
                    <CalendarCheck className="size-5" aria-hidden />
                </span>
                <span className="flex min-w-0 flex-col">
                    <span className="flex items-center gap-2 text-xs font-semibold tracking-wide text-brand-text uppercase">
                        {HERO_CARDS.booking.label}
                        <span className="font-normal tracking-normal text-muted-foreground normal-case">
                            {HERO_CARDS.booking.when}
                        </span>
                    </span>
                    <span className="text-sm font-semibold">
                        {HERO_CARDS.booking.title}
                    </span>
                    <span className="text-sm text-muted-foreground tabular-nums">
                        {HERO_CARDS.booking.detail}
                    </span>
                </span>
            </motion.div>

            <motion.div
                {...float(0.5, 12)}
                className="absolute right-1 bottom-0 flex items-center gap-3 rounded-2xl px-4 py-3 skin-raised sm:right-2 lg:-right-6"
            >
                <BadgeCheck className="size-6 text-success" aria-hidden />
                <span className="flex flex-col">
                    <span className="text-sm text-muted-foreground">
                        {HERO_CARDS.deposit.label}
                    </span>
                    <span className="text-base font-semibold tabular-nums">
                        {HERO_CARDS.deposit.amount}
                    </span>
                </span>
            </motion.div>
        </div>
    );
}
