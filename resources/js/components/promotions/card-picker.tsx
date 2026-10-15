import {
    ArrowRight,
    CalendarHeart,
    Gift,
    Layers,
    Percent,
    Sunrise,
    UserPlus,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { cardOffers } from '@/lib/promotion-cards';
import type { PromotionCardKind, PromotionService } from './types';

const ICONS: Record<PromotionCardKind, LucideIcon> = {
    discount: Percent,
    gift: Gift,
    quiet_hours: Sunrise,
    season: CalendarHeart,
    first_visit: UserPlus,
    combo: Layers,
    pair: Users,
};

type Props = {
    services: PromotionService[];
    today: string;
    onPick: (card: PromotionCardKind) => void;
};

/**
 * The kinds of promotion, each with an example written with the
 * salon's own services. Picking one opens its form already filled in.
 * An odd one out at the end takes the whole row.
 */
export function CardPicker({ services, today, onPick }: Props) {
    const reduceMotion = useReducedMotion();

    return (
        <ul className="grid gap-3 sm:grid-cols-2">
            {cardOffers(services, today).map((offer, index) => {
                const Icon = ICONS[offer.card];

                return (
                    <motion.li
                        key={offer.card}
                        className="sm:odd:last:col-span-2"
                        initial={reduceMotion ? false : { opacity: 0, y: 12 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{
                            duration: 0.25,
                            delay: index * 0.06,
                            ease: [0.22, 1, 0.36, 1],
                        }}
                    >
                        <button
                            type="button"
                            onClick={() => onPick(offer.card)}
                            className="group flex h-full w-full flex-col gap-3 rounded-xl border bg-card p-4 text-left shadow-card transition-colors outline-none hover:border-brand-line hover:bg-surface-2 focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <span className="flex items-center gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-text">
                                    <Icon className="size-5" aria-hidden />
                                </span>
                                <span className="font-display text-lg font-semibold">
                                    {offer.title}
                                </span>
                                <ArrowRight
                                    className="ml-auto size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none"
                                    aria-hidden
                                />
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {offer.pitch}
                            </span>
                            {offer.example && (
                                <span className="mt-auto rounded-lg bg-band px-3 py-2 text-sm">
                                    <span className="text-muted-foreground">
                                        Por ejemplo:{' '}
                                    </span>
                                    <span className="font-medium">
                                        {offer.example}
                                    </span>
                                </span>
                            )}
                        </button>
                    </motion.li>
                );
            })}
        </ul>
    );
}
