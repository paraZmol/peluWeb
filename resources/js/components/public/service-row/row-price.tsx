import { formatPrice } from '@/lib/format';
import { offerNote } from '@/lib/promo-offers';
import type { ServiceOffer } from '@/lib/promo-offers';
import type { PublicService } from '../types';

type Props = {
    service: PublicService;
    /** Its price with the salon's best promotion, when one lowers it. */
    offer?: ServiceOffer;
    /** Classes of the "desde" before a price that depends on an option. */
    fromClassName: string;
};

/**
 * A service's price; with a promotion, the normal one crossed out first
 * and, when only some times get it, a word saying so.
 */
export function RowPrice({ service, offer: anyOffer, fromClassName }: Props) {
    // A combo's service alone keeps its price: nothing to cross out.
    const offer = anyOffer?.isCombo ? undefined : anyOffer;
    const note = offerNote(offer);

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
            {note && (
                <span className="block text-xs font-normal text-muted-foreground">
                    {note}
                </span>
            )}
        </>
    );
}
