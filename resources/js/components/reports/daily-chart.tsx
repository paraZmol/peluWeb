import { useId, useState } from 'react';
import { axisTicks, bucketDays, bucketTick, formatAxis } from '@/lib/reports';
import type { Grain } from '@/lib/reports';
import { cn } from '@/lib/utils';
import { ChartBars, ExpenseHatch } from './chart-bars';
import { ChartReadout } from './chart-readout';
import { ChartTable } from './chart-table';
import { ReportSection, SectionEmpty } from './report-section';
import type { ReportDay } from './types';

/** How many labels fit under the bars on a phone. */
const PHONE_TICKS = 7;

const TITLE: Record<Grain, string> = {
    day: 'Ingresos y gastos por día',
    week: 'Ingresos y gastos por semana',
    month: 'Ingresos y gastos por mes',
};

const GROUPING: Record<Grain, string | undefined> = {
    day: undefined,
    week: 'Son muchos días para verlos uno por uno: van sumados por semana, de lunes a domingo.',
    month: 'Son muchos días para verlos uno por uno: van sumados por mes.',
};

type Props = { days: ReportDay[] };

/** When the money came in and went out, as two bars per day, week or month. */
export function DailyChart({ days }: Props) {
    const hatchId = useId();
    const [active, setActive] = useState<number | null>(null);
    const { grain, buckets } = bucketDays(days);
    const biggest = Math.max(
        ...buckets.map((b) => Math.max(b.incomeCents, b.expensesCents)),
        0,
    );
    const ticks = axisTicks(biggest);
    const maxCents = ticks[ticks.length - 1];
    const phoneStep = Math.ceil(buckets.length / PHONE_TICKS);
    const shown = active === null ? null : (buckets[active] ?? null);

    return (
        <ReportSection title={TITLE[grain]} description={GROUPING[grain]}>
            {biggest === 0 ? (
                <SectionEmpty>
                    No hubo ingresos ni gastos en estos días.
                </SectionEmpty>
            ) : (
                <>
                    <div className="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
                        <span className="inline-flex items-center gap-2">
                            <span
                                aria-hidden
                                className="size-3 rounded-[3px] bg-primary"
                            />
                            Ingresos
                        </span>
                        <span className="inline-flex items-center gap-2">
                            <svg aria-hidden className="size-3">
                                <defs>
                                    <ExpenseHatch id={hatchId} />
                                </defs>
                                <rect
                                    width="100%"
                                    height="100%"
                                    rx="3"
                                    fill={`url(#${hatchId})`}
                                    className="stroke-muted-foreground"
                                />
                            </svg>
                            Gastos
                        </span>
                    </div>

                    <ChartReadout bucket={shown} grain={grain} />

                    <div className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-2 gap-y-1.5 text-xs text-muted-foreground tabular-nums">
                        <div aria-hidden className="relative h-56">
                            <span className="invisible">
                                {formatAxis(maxCents)}
                            </span>
                            {ticks.map((tick) => (
                                <span
                                    key={tick}
                                    className="absolute right-0 translate-y-1/2 whitespace-nowrap"
                                    style={{
                                        bottom: `${(tick * 100) / maxCents}%`,
                                    }}
                                >
                                    {formatAxis(tick)}
                                </span>
                            ))}
                        </div>
                        <ChartBars
                            buckets={buckets}
                            maxCents={maxCents}
                            ticks={ticks}
                            active={active}
                            onActive={setActive}
                            label={`Gráfico de barras: ${TITLE[grain].toLowerCase()}. Los valores exactos están en la tabla que sigue.`}
                        />
                        <div aria-hidden className="col-start-2 flex">
                            {buckets.map((bucket, index) => (
                                <span
                                    key={bucket.from}
                                    className={cn(
                                        'flex min-w-0 flex-1 justify-center whitespace-nowrap',
                                        index % phoneStep !== 0 &&
                                            'invisible sm:visible',
                                    )}
                                >
                                    {bucketTick(bucket, grain)}
                                </span>
                            ))}
                        </div>
                    </div>

                    <ChartTable buckets={buckets} grain={grain} />
                </>
            )}
        </ReportSection>
    );
}
