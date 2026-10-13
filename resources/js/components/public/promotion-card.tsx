import { Link } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Clock, Gift, Percent } from 'lucide-react';
import { formatLongDate, formatPrice } from '@/lib/format';
import { PRIMARY_ACTION, SOFT_ACTION } from '@/lib/skin-actions';
import { cn } from '@/lib/utils';
import { create as bookingWizard } from '@/routes/salons/booking';
import { PromotionPrices } from './promotion-prices';
import { PromotionTerms } from './promotion-terms';
import type { PublicPromotion } from './types';

type Props = {
    promotion: PublicPromotion;
    /** Names of the salon's services, to label each price. */
    serviceNames: Map<number, string>;
    /** Null when the salon takes no online bookings: the card only informs. */
    salonSlug: string | null;
    /** The one the visitor's link points at. */
    isFeatured: boolean;
    className?: string;
};

/** One promotion as the visitor reads it: what, how much, until when. */
export function PromotionCard({
    promotion,
    serviceNames,
    salonSlug,
    isFeatured,
    className,
}: Props) {
    const { gift } = promotion;
    const Icon = gift ? Gift : Percent;

    return (
        <li
            aria-label={promotion.name}
            className={cn(
                'relative flex flex-col gap-4 overflow-hidden rounded-xl p-5 sm:p-6',
                isFeatured ? 'skin-raised' : 'skin-card',
                className,
            )}
        >
            {isFeatured && (
                <span
                    aria-hidden
                    className="absolute inset-x-0 top-0 h-1.5 bg-primary"
                />
            )}
            <div className="flex items-start gap-3.5">
                <span
                    aria-hidden
                    className="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-text"
                >
                    <Icon className="size-6" />
                </span>
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    {isFeatured && (
                        <p className="mb-1 w-fit rounded-full skin-primary px-3 py-1 text-xs font-bold tracking-[.12em] uppercase">
                            Tu promoción
                        </p>
                    )}
                    {promotion.overline && (
                        <p className="text-xs font-bold tracking-[.16em] text-brand-text uppercase">
                            {promotion.overline}
                        </p>
                    )}
                    <h3 className="display-type text-title-3 break-words">
                        {promotion.name}
                    </h3>
                </div>
            </div>

            {gift ? (
                <p className="rounded-lg bg-brand-soft px-3.5 py-3 font-semibold text-brand-text">
                    {gift.name} gratis ·{' '}
                    <span className="whitespace-nowrap tabular-nums">
                        Ahorras {formatPrice(gift.price)}
                    </span>
                </p>
            ) : (
                <PromotionPrices
                    prices={promotion.prices}
                    serviceNames={serviceNames}
                />
            )}

            <ul className="flex flex-col gap-1.5 text-sm text-muted-foreground">
                {promotion.when && (
                    <li className="flex items-start gap-2">
                        <Clock className="mt-0.5 size-4 shrink-0" aria-hidden />
                        <span className="first-letter:uppercase">
                            {promotion.when}
                        </span>
                    </li>
                )}
                <li className="flex items-start gap-2">
                    <CalendarDays
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden
                    />
                    Hasta el {formatLongDate(promotion.endsOn)}
                </li>
            </ul>

            <div className="mt-auto flex flex-col gap-1">
                <PromotionTerms text={promotion.finePrint} />
                {salonSlug && (
                    <Link
                        href={bookingWizard(salonSlug, {
                            query: { promo: promotion.id },
                        })}
                        aria-label={`Reservar con esta promo: ${promotion.name}`}
                        className={cn(
                            isFeatured ? PRIMARY_ACTION : SOFT_ACTION,
                            'h-12 w-full sm:w-fit',
                        )}
                    >
                        Reservar con esta promo <ArrowRight aria-hidden />
                    </Link>
                )}
            </div>
        </li>
    );
}
