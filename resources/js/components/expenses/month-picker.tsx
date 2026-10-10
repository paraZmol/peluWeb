import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { shiftMonth } from '@/lib/expenses';
import { formatMonth } from '@/lib/format';
import { index } from '@/routes/expenses';

type Props = {
    /** The month on screen, "2026-10". */
    month: string;
    /** The salon's current month: there is nothing after it. */
    currentMonth: string;
};

/** One month back or forward, never past the current one. */
export function MonthPicker({ month, currentMonth }: Props) {
    const isCurrent = month >= currentMonth;
    const to = (by: number) => index({ query: { mes: shiftMonth(month, by) } });

    return (
        <nav
            aria-label="Mes"
            className="flex items-center justify-between gap-2 sm:justify-start"
        >
            <Button variant="outline" size="icon" asChild>
                <Link href={to(-1)} preserveScroll aria-label="Mes anterior">
                    <ChevronLeft aria-hidden />
                </Link>
            </Button>
            <p
                aria-live="polite"
                className="min-w-40 text-center font-medium tabular-nums"
            >
                {formatMonth(month)}
            </p>
            {isCurrent ? (
                <Button
                    variant="outline"
                    size="icon"
                    disabled
                    aria-label="Mes siguiente"
                >
                    <ChevronRight aria-hidden />
                </Button>
            ) : (
                <Button variant="outline" size="icon" asChild>
                    <Link
                        href={to(1)}
                        preserveScroll
                        aria-label="Mes siguiente"
                    >
                        <ChevronRight aria-hidden />
                    </Link>
                </Button>
            )}
        </nav>
    );
}
