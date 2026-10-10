import { useId } from 'react';
import type { PointerEvent } from 'react';
import type { Bucket } from '@/lib/reports';

/** Part of a bucket's width each bar takes, and the most it may grow to. */
const BAR_SHARE = 0.34;
const MAX_BAR_PERCENT = 4;
const GAP_SHARE = 0.06;
const MAX_GAP_PERCENT = 0.4;
/** A bar with money in it always shows, however small next to the rest. */
const MIN_BAR_PERCENT = 1;

type Props = {
    buckets: Bucket[];
    /** The top of the amount axis, in céntimos. */
    maxCents: number;
    ticks: number[];
    active: number | null;
    onActive: (index: number | null) => void;
    label: string;
};

/**
 * The hatch that fills the expense bars and their legend mark: income and
 * expenses differ in texture too, so the chart reads without color (printed
 * in gray, or with a brand color close to the neutral one).
 */
export function ExpenseHatch({ id }: { id: string }) {
    return (
        <pattern
            id={id}
            width="5"
            height="5"
            patternUnits="userSpaceOnUse"
            patternTransform="rotate(45)"
        >
            <line
                x1="1.25"
                x2="1.25"
                y2="5"
                strokeWidth="2.5"
                className="stroke-muted-foreground"
            />
        </pattern>
    );
}

/**
 * Two bars per bucket, drawn in percents of the box: it fits any width
 * without measuring, and the text around it stays crisp.
 */
export function ChartBars({
    buckets,
    maxCents,
    ticks,
    active,
    onActive,
    label,
}: Props) {
    const hatchId = useId();
    const slot = 100 / buckets.length;
    const bar = Math.min(slot * BAR_SHARE, MAX_BAR_PERCENT);
    const gap = Math.min(slot * GAP_SHARE, MAX_GAP_PERCENT);
    const height = (cents: number) =>
        cents <= 0 ? 0 : Math.max((cents * 100) / maxCents, MIN_BAR_PERCENT);
    // A finger leaves the bar as soon as it lifts: only the mouse clears it.
    const leave = (event: PointerEvent) =>
        event.pointerType === 'mouse' && onActive(null);

    return (
        <svg
            role="img"
            aria-label={label}
            className="h-56 w-full overflow-visible"
            onPointerLeave={leave}
        >
            <defs>
                <ExpenseHatch id={hatchId} />
            </defs>

            {ticks.map((tick) => (
                <line
                    key={tick}
                    x2="100%"
                    y1={`${100 - (tick * 100) / maxCents}%`}
                    y2={`${100 - (tick * 100) / maxCents}%`}
                    className={
                        tick === 0 ? 'stroke-border-strong' : 'stroke-border'
                    }
                />
            ))}

            {buckets.map((bucket, index) => {
                const center = slot * (index + 0.5);
                const income = height(bucket.incomeCents);
                const expenses = height(bucket.expensesCents);

                return (
                    <g key={bucket.from}>
                        {active === index && (
                            <rect
                                x={`${slot * index}%`}
                                width={`${slot}%`}
                                height="100%"
                                className="fill-muted"
                            />
                        )}
                        <rect
                            x={`${center - gap / 2 - bar}%`}
                            y={`${100 - income}%`}
                            width={`${bar}%`}
                            height={`${income}%`}
                            rx="1.5"
                            className="fill-primary"
                        />
                        <rect
                            x={`${center + gap / 2}%`}
                            y={`${100 - expenses}%`}
                            width={`${bar}%`}
                            height={`${expenses}%`}
                            rx="1.5"
                            fill={`url(#${hatchId})`}
                            className="stroke-muted-foreground"
                        />
                        <rect
                            x={`${slot * index}%`}
                            width={`${slot}%`}
                            height="100%"
                            fill="transparent"
                            onPointerEnter={() => onActive(index)}
                            onPointerDown={() => onActive(index)}
                        />
                    </g>
                );
            })}
        </svg>
    );
}
