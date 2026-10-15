import { formatPrice } from '@/lib/format';
import type { PromotionPrice } from './types';

/** More prices than this would bury the card: the rest are counted. */
const PRICES_SHOWN = 3;

type Props = {
    prices: PromotionPrice[];
    /** Names of the salon's services, to label each price. */
    serviceNames: Map<number, string>;
};

/** What each service of a discount costs: the normal price, then the new one. */
export function PromotionPrices({ prices, serviceNames }: Props) {
    const extra = prices.length - PRICES_SHOWN;

    return (
        <div>
            <ul className="divide-y">
                {prices.slice(0, PRICES_SHOWN).map((price) => (
                    <li
                        key={price.serviceId}
                        className="flex items-baseline justify-between gap-3 py-2"
                    >
                        <span className="min-w-0 break-words">
                            {/* Add-ons are not in the page's list of services. */}
                            {serviceNames.get(price.serviceId) ?? price.name}
                        </span>
                        <span className="flex shrink-0 items-baseline gap-2 tabular-nums">
                            {price.isFrom && (
                                <span className="text-sm text-muted-foreground">
                                    desde
                                </span>
                            )}
                            <s className="text-sm text-muted-foreground">
                                <span className="sr-only">Precio normal </span>
                                {formatPrice(price.normal)}
                            </s>
                            <span className="text-lg font-extrabold text-brand-text">
                                <span className="sr-only">Con promoción </span>
                                {formatPrice(price.promo)}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
            {extra > 0 && (
                <p className="text-sm text-muted-foreground">
                    y {extra} {extra === 1 ? 'servicio' : 'servicios'} más
                </p>
            )}
        </div>
    );
}
