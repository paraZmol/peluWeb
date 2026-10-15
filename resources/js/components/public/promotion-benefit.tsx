import { formatPrice } from '@/lib/format';
import { PairBenefit } from './pair-benefit';
import { PromotionPrices } from './promotion-prices';
import type { PublicPromotion } from './types';

type Props = {
    promotion: PublicPromotion;
    /** Names of the salon's services, to label each price. */
    serviceNames: Map<number, string>;
};

/**
 * What the promotion gives, the way each kind says it best: the gift and
 * what it is worth, the combo's price against its services apart, or the
 * price of each discounted service.
 */
export function PromotionBenefit({ promotion, serviceNames }: Props) {
    const { gift, combo } = promotion;

    if (gift) {
        return (
            <p className="rounded-lg bg-brand-soft px-3.5 py-3 font-semibold text-brand-text">
                {gift.name} gratis ·{' '}
                <span className="whitespace-nowrap tabular-nums">
                    Ahorras {formatPrice(gift.price)}
                </span>
            </p>
        );
    }

    if (promotion.mechanic === 'pair') {
        return (
            <PairBenefit promotion={promotion} serviceNames={serviceNames} />
        );
    }

    if (combo) {
        const from = combo.isFrom ? ' desde' : '';

        return (
            <div className="flex flex-col gap-1 rounded-lg bg-brand-soft px-3.5 py-3">
                <p className="flex items-baseline gap-2 text-brand-text tabular-nums">
                    <span className="text-sm font-semibold">
                        {combo.isFrom ? 'Desde' : 'Todo por'}
                    </span>
                    <span className="text-3xl leading-none font-extrabold">
                        {formatPrice(combo.price)}
                    </span>
                </p>
                <p className="text-sm font-semibold tabular-nums">
                    Por separado{from} {formatPrice(combo.normal)}: ahorras{' '}
                    {formatPrice(combo.saves)}
                </p>
            </div>
        );
    }

    return (
        <PromotionPrices
            prices={promotion.prices}
            serviceNames={serviceNames}
        />
    );
}
