import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { periodShortcuts, yearBefore } from '@/lib/commissions';
import { cn } from '@/lib/utils';
import { index } from '@/routes/commissions';
import type { Period } from './types';

type Props = {
    period: Period;
    /** Today on the salon's calendar: there is nothing after it. */
    today: string;
};

const toPeriod = ({ from, to }: Period) =>
    index({ query: { desde: from, hasta: to } });

/** The days on screen: the usual periods one tap away, or any two dates. */
export function PeriodPicker({ period, today }: Props) {
    // What is being typed: a half-written date must not reload the page.
    const [draft, setDraft] = useState(period);
    const [shown, setShown] = useState(period);

    if (shown.from !== period.from || shown.to !== period.to) {
        setShown(period);
        setDraft(period);
    }

    const change = (edge: keyof Period, day: string) => {
        const next = { ...draft, [edge]: day };
        setDraft(next);

        if (day === '' || day > today || day < yearBefore(today)) {
            return;
        }

        // The other end follows, so the range is never backwards.
        if (next.from > next.to) {
            next[edge === 'from' ? 'to' : 'from'] = day;
        }

        router.visit(toPeriod(next), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <nav
                aria-label="Períodos habituales"
                className="grid grid-cols-3 gap-2 sm:flex"
            >
                {periodShortcuts(today).map((shortcut) => {
                    const isCurrent =
                        shortcut.from === period.from &&
                        shortcut.to === period.to;

                    return (
                        <Link
                            key={shortcut.label}
                            href={toPeriod(shortcut)}
                            preserveScroll
                            preserveState
                            aria-current={isCurrent ? 'true' : undefined}
                            className={cn(
                                'flex min-h-11 items-center justify-center rounded-full border px-2 text-center text-sm font-medium transition-colors outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 sm:px-4',
                                isCurrent &&
                                    'border-primary bg-brand-soft ring-1 ring-primary ring-inset hover:bg-brand-soft',
                            )}
                        >
                            {shortcut.label}
                        </Link>
                    );
                })}
            </nav>

            <div className="grid grid-cols-2 gap-3 sm:flex">
                <div className="grid gap-1.5">
                    <Label htmlFor="period-from">Desde</Label>
                    <Input
                        id="period-from"
                        type="date"
                        min={yearBefore(draft.to || today)}
                        max={today}
                        value={draft.from}
                        onChange={(e) => change('from', e.target.value)}
                        className="h-11 sm:w-44"
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="period-to">Hasta</Label>
                    <Input
                        id="period-to"
                        type="date"
                        max={today}
                        value={draft.to}
                        onChange={(e) => change('to', e.target.value)}
                        className="h-11 sm:w-44"
                    />
                </div>
            </div>
        </div>
    );
}
