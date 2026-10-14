import { formatPrice } from '@/lib/format';
import { LIMITED_PRICE_NOTE } from '@/lib/promo-offers';
import type { ServiceOffer } from '@/lib/promo-offers';

type Props = {
    /** The lowest it can cost: its cheapest option, else its own price. */
    price: string;
    hasOptions: boolean;
    /** Its price with the salon's best promotion, when one lowers it. */
    offer?: ServiceOffer;
    /** Chosen as the gift of a promotion: it costs nothing. */
    isGift: boolean;
};

/** Price of a service to tick; the normal one crossed out with a promotion. */
export function OptionPrice({ price, hasOptions, offer, isGift }: Props) {
    const normal = isGift ? price : offer?.normal;

    return (
        <span className="shrink-0 text-right font-bold tabular-nums">
            {hasOptions && (
                <span className="block text-xs font-normal text-muted-foreground">
                    desde
                </span>
            )}
            {normal && (
                <s className="block text-sm font-normal text-muted-foreground">
                    <span className="sr-only">Precio normal </span>
                    {formatPrice(normal)}
                </s>
            )}
            <span className={normal && 'text-brand-text'}>
                {normal && <span className="sr-only">Con promoción </span>}
                {isGift ? 'Gratis' : formatPrice(offer?.promo ?? price)}
            </span>
            {!isGift && offer?.isLimited && (
                <span className="block text-xs font-normal text-muted-foreground">
                    {LIMITED_PRICE_NOTE}
                </span>
            )}
        </span>
    );
}
