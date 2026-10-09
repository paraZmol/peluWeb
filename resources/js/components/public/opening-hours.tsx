import { useSalonTheme } from '@/components/salon/brand-theme';
import type { SalonThemeLayout } from '@/components/salon/salon-themes';
import { formatTime } from '@/lib/format';
import { WEEKDAYS } from '@/lib/schedule';
import { cn } from '@/lib/utils';
import { SectionHeading } from './section-heading';
import type { PublicOpeningHours } from './types';

/** How "today" stands out in each template (`layout.highlight`). */
const TODAY: Record<SalonThemeLayout['highlight'], string> = {
    outline: 'border border-brand-line bg-brand-soft font-semibold',
    soft: 'bg-brand-soft font-semibold text-brand-text',
    solid: 'skin-primary font-semibold',
};

export function OpeningHours({ hours }: { hours: PublicOpeningHours }) {
    const { layout } = useSalonTheme();

    return (
        <section
            aria-labelledby="horario-titulo"
            className="flex flex-col gap-6"
        >
            <SectionHeading
                id="horario-titulo"
                eyebrow="Visítanos"
                title="Horario de atención"
                compact
            />
            <dl className="flex flex-col gap-0.5 rounded-xl p-2 skin-card">
                {hours.days.map((day) => {
                    const isToday = day.weekday === hours.today;

                    return (
                        <div
                            key={day.weekday}
                            className={cn(
                                'flex items-start justify-between gap-4 rounded-lg px-3 py-2.5 text-sm',
                                isToday && TODAY[layout.highlight],
                            )}
                        >
                            <dt
                                className={cn(
                                    'flex items-center gap-2',
                                    !isToday && 'text-muted-foreground',
                                )}
                            >
                                {WEEKDAYS[day.weekday - 1].label}
                                {isToday && layout.highlight === 'outline' && (
                                    <span className="rounded-full skin-primary px-2 py-0.5 text-[0.6875rem] font-bold tracking-wider uppercase">
                                        Hoy
                                    </span>
                                )}
                                {isToday && layout.highlight !== 'outline' && (
                                    <span>· hoy</span>
                                )}
                            </dt>
                            <dd
                                className={cn(
                                    'text-right tabular-nums',
                                    day.ranges.length === 0 &&
                                        !isToday &&
                                        'text-muted-foreground',
                                )}
                            >
                                {day.ranges.length === 0
                                    ? 'Cerrado'
                                    : day.ranges.map((range) => (
                                          <span
                                              key={range.start}
                                              className="block"
                                          >
                                              {formatTime(range.start)} –{' '}
                                              {formatTime(range.end)}
                                          </span>
                                      ))}
                            </dd>
                        </div>
                    );
                })}
            </dl>
        </section>
    );
}
