import { formatCents } from '@/lib/format';
import { shareOf } from '@/lib/reports';

/** The thinnest bar that still shows: a tiny share is not nothing. */
const MIN_BAR_PERCENT = 1;

export type Share = {
    name: string;
    cents: number;
    /** Under the name: "3 veces", "2 citas". */
    detail?: string;
};

type Props = {
    items: Share[];
    /** What the percents are taken from; the items' sum when left out. */
    totalCents?: number;
};

/** Amounts side by side: each with its part of the total, as text and bar. */
export function ShareList({ items, totalCents }: Props) {
    const total =
        totalCents ?? items.reduce((sum, item) => sum + item.cents, 0);

    return (
        <ul className="flex flex-col gap-3">
            {items.map((item) => {
                const percent = shareOf(item.cents, total);

                return (
                    <li
                        key={item.name}
                        className="flex break-inside-avoid flex-col gap-1"
                    >
                        <div className="flex items-baseline justify-between gap-3 text-sm">
                            <span className="min-w-0 break-words">
                                {item.name}
                                {item.detail && (
                                    <span className="text-muted-foreground">
                                        {' · '}
                                        {item.detail}
                                    </span>
                                )}
                            </span>
                            <span className="shrink-0 tabular-nums">
                                <span className="font-medium">
                                    {formatCents(item.cents)}
                                </span>
                                <span className="ml-2 inline-block w-12 text-right whitespace-nowrap text-muted-foreground">
                                    {percent} %
                                </span>
                            </span>
                        </div>
                        <div
                            aria-hidden
                            className="h-2 overflow-hidden rounded-full bg-muted"
                        >
                            {item.cents > 0 && (
                                <div
                                    className="h-full rounded-full bg-primary"
                                    style={{
                                        width: `${Math.max(percent, MIN_BAR_PERCENT)}%`,
                                    }}
                                />
                            )}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
