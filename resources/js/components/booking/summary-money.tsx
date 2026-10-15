import { formatPrice } from '@/lib/format';
import { toCents } from '@/lib/money';
import { PromotionLine } from './promotion-line';

export type SummaryItem = {
    name: string;
    price?: string;
    /** The normal price, when a promotion changed what is charged. */
    listPrice?: string | null;
};

type Props = {
    items: SummaryItem[];
    total: string | null;
    /** e.g. "desde" when the price depends on who attends. */
    totalPrefix?: string;
    /** The total without the promotion, shown crossed out. */
    listTotal?: string | null;
    /** The promotion applied and what it takes off. */
    promotion?: { name: string; amount: string } | null;
};

/** What is booked and what it costs, with the promotion on its own line. */
export function SummaryMoney({
    items,
    total,
    totalPrefix,
    listTotal,
    promotion,
}: Props) {
    return (
        <>
            <ul className="flex flex-col gap-1.5">
                {items.map((item) => (
                    <li key={item.name} className="flex justify-between gap-3">
                        <span className="min-w-0 break-words">{item.name}</span>
                        {item.price && (
                            <span className="shrink-0 text-muted-foreground tabular-nums">
                                {item.listPrice && (
                                    <s className="mr-2 text-sm">
                                        <span className="sr-only">
                                            Precio normal{' '}
                                        </span>
                                        {formatPrice(item.listPrice)}
                                    </s>
                                )}
                                {item.listPrice && toCents(item.price) === 0 ? (
                                    <span className="font-semibold text-brand-text">
                                        Gratis
                                    </span>
                                ) : (
                                    formatPrice(item.price)
                                )}
                            </span>
                        )}
                    </li>
                ))}
            </ul>

            {promotion && (
                <PromotionLine
                    name={promotion.name}
                    amount={promotion.amount}
                />
            )}

            {total !== null && (
                <div className="flex items-baseline justify-between border-t border-dashed pt-3">
                    <dt className="font-medium">Total</dt>
                    <dd className="text-xl font-extrabold tabular-nums">
                        {totalPrefix && (
                            <span className="text-sm font-normal text-muted-foreground">
                                {totalPrefix}{' '}
                            </span>
                        )}
                        {listTotal && (
                            <s className="mr-2 text-sm font-normal text-muted-foreground">
                                <span className="sr-only">Precio normal </span>
                                {formatPrice(listTotal)}
                            </s>
                        )}
                        {formatPrice(total)}
                    </dd>
                </div>
            )}
        </>
    );
}
