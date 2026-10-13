import { formatPrice } from '@/lib/format';
import type { ServiceOffer } from '@/lib/promo-offers';
import type { PublicService } from '../types';

type Props = {
    service: PublicService;
    /** Its price with the salon's best promotion, when one lowers it. */
    offer?: ServiceOffer;
    /** Classes of the "desde" before a price that depends on an option. */
    fromClassName: string;
};

/** A service's price; with a promotion, the normal one crossed out first. */
export function RowPrice({ service, offer, fromClassName }: Props) {
    return (
        <>
            {service.hasVariants && (
                <span className={fromClassName}>desde</span>
            )}
            {offer && (
                <>
                    <s className="mr-1.5 text-sm font-normal text-muted-foreground">
                        <span className="sr-only">Precio normal </span>
                        {formatPrice(offer.normal)}
                    </s>
                    <span className="sr-only">Con promoción </span>
                </>
            )}
            <span className={offer && 'text-brand-text'}>
                {formatPrice(offer?.promo ?? service.priceFrom)}
            </span>
        </>
    );
}
