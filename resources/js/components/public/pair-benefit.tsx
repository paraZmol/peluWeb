import { formatPrice } from '@/lib/format';
import { fromCents, toCents } from '@/lib/money';
import { pairPercent } from '@/lib/promo-offers';
import { PAIR_FREE, percentPrice } from '@/lib/promotions';
import type { PublicPromotion } from './types';

/** More services than this would bury the card: the rest are counted. */
const PRICES_SHOWN = 2;

type Props = {
    promotion: PublicPromotion;
    /** Names of the salon's services, to label each price. */
    serviceNames: Map<number, string>;
};

/**
 * "Ven con alguien" as the visitor reads it: what the second one gets, what
 * two of each service cost together, and who pays whole — without opening
 * the conditions.
 */
export function PairBenefit({ promotion, serviceNames }: Props) {
    const percent = pairPercent(promotion) ?? 0;
    const isFree = percent === PAIR_FREE;
    const extra = promotion.prices.length - PRICES_SHOWN;

    return (
        <div className="flex flex-col gap-2 rounded-lg bg-brand-soft px-3.5 py-3">
            <p className="flex items-baseline gap-2 text-brand-text">
                {isFree && (
                    <span className="text-3xl leading-none font-extrabold">
                        2x1
                    </span>
                )}
                <span className="font-semibold">
                    {isFree
                        ? 'Vienen dos, el segundo gratis'
                        : 'Vienen dos, el segundo a mitad de precio'}
                </span>
            </p>
            <ul className="flex flex-col gap-1 text-sm tabular-nums">
                {promotion.prices.slice(0, PRICES_SHOWN).map((price) => {
                    const second = percentPrice(
                        toCents(price.normal) ?? 0,
                        percent,
                    );

                    return (
                        <li key={price.serviceId} className="break-words">
                            <span className="font-semibold">
                                {serviceNames.get(price.serviceId) ??
                                    price.name}{' '}
                                para dos:
                            </span>{' '}
                            {price.isFrom && 'desde '}
                            <span className="whitespace-nowrap">
                                {formatPrice(price.normal)} +
                            </span>{' '}
                            <span className="whitespace-nowrap">
                                {isFree
                                    ? 'gratis'
                                    : formatPrice(fromCents(second))}
                            </span>
                        </li>
                    );
                })}
                {extra > 0 && (
                    <li className="text-muted-foreground">
                        y {extra} {extra === 1 ? 'servicio' : 'servicios'} más
                    </li>
                )}
            </ul>
            <p className="text-sm text-muted-foreground">
                Se cobra entero el de mayor precio. Les atiende la misma
                persona, uno después del otro.
            </p>
        </div>
    );
}
