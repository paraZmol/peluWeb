import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { StaffSelect } from '@/components/booking/staff-select';
import type { StaffOption } from '@/components/booking/types';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create } from '@/routes/agenda/bookings';

export type AgendaView = 'lista' | 'tablero' | 'semana';

type Props = {
    view: AgendaView;
    date: string;
    today: string;
    /** First and last day of the week shown, in the week view. */
    week: { from: string; to: string } | null;
    staffOptions: StaffOption[];
    selectedStaffId: number | null;
    seesEveryone: boolean;
    canBook: boolean;
    onDate: (date: string) => void;
    onStaff: (staffId: number | null) => void;
    onView: (view: AgendaView) => void;
};

const DAYS_PER_WEEK = 7;
/** Thumb-sized and sharing the row on a phone; compact on wider screens. */
const VIEW_ITEM = 'h-11 flex-1 px-3 sm:h-9 sm:flex-none';

export function shiftDate(date: string, days: number): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + days))
        .toISOString()
        .slice(0, 10);
}

/** "5 – 11 oct", or "28 sep – 4 oct" when the week crosses a month. */
function weekRange(from: string, to: string): string {
    const [first, last] = [from, to].map((day) =>
        formatDate(day).split(' ').slice(1),
    );

    return first[1] === last[1]
        ? `${first[0]} – ${last.join(' ')}`
        : `${first.join(' ')} – ${last.join(' ')}`;
}

/** Where the agenda is looking: which day or week, whose, and how. */
export function AgendaToolbar({
    view,
    date,
    today,
    week,
    staffOptions,
    selectedStaffId,
    seesEveryone,
    canBook,
    onDate,
    onStaff,
    onView,
}: Props) {
    const stride = view === 'semana' ? DAYS_PER_WEEK : 1;
    const unit = view === 'semana' ? 'Semana' : 'Día';
    const hasTeam = seesEveryone && staffOptions.length > 1;
    const isCurrent = week
        ? week.from <= today && today <= week.to
        : date === today;
    const period = week ? weekRange(week.from, week.to) : formatDate(date);

    return (
        <>
            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    aria-label={`${unit} anterior`}
                    onClick={() => onDate(shiftDate(date, -stride))}
                >
                    <ChevronLeft />
                </Button>
                <div className="flex min-w-0 flex-1 flex-col items-center text-center">
                    <h1
                        className={cn(
                            'text-xl leading-7 font-semibold text-balance',
                            !isCurrent && 'capitalize',
                        )}
                    >
                        {isCurrent ? (week ? 'Esta semana' : 'Hoy') : period}
                    </h1>
                    {isCurrent ? (
                        <p className="text-sm leading-6 text-muted-foreground capitalize tabular-nums">
                            {period}
                        </p>
                    ) : (
                        <button
                            type="button"
                            onClick={() => onDate(today)}
                            className="relative rounded-sm text-sm leading-6 font-medium underline decoration-primary decoration-2 underline-offset-4 after:absolute after:-inset-x-4 after:-inset-y-2.5 hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            Volver a hoy
                        </button>
                    )}
                </div>
                <Button
                    variant="outline"
                    size="icon"
                    aria-label={`${unit} siguiente`}
                    onClick={() => onDate(shiftDate(date, stride))}
                >
                    <ChevronRight />
                </Button>
            </div>

            {/* Phone: the view on top, then whose and "new". Wider: one row. */}
            <div className="flex flex-wrap items-center gap-2">
                {canBook && (
                    <Button
                        asChild
                        className={cn(
                            'order-3 rounded-full sm:order-1',
                            !hasTeam && 'max-sm:flex-1',
                        )}
                    >
                        <Link href={create()}>
                            <Plus aria-hidden /> Nueva cita
                        </Link>
                    </Button>
                )}
                {hasTeam && (
                    <StaffSelect
                        className="order-2 min-w-0 flex-1 data-[size=default]:h-11 sm:w-auto sm:min-w-44 sm:flex-none sm:data-[size=default]:h-9"
                        options={staffOptions}
                        value={selectedStaffId}
                        everyoneLabel={
                            view === 'semana' ? undefined : 'Todo el equipo'
                        }
                        onChange={onStaff}
                    />
                )}
                <ToggleGroup
                    type="single"
                    variant="outline"
                    className="order-1 w-full sm:order-3 sm:ml-auto sm:w-auto"
                    aria-label="Vista de la agenda"
                    value={view}
                    onValueChange={(next) => next && onView(next as AgendaView)}
                >
                    <ToggleGroupItem value="lista" className={VIEW_ITEM}>
                        Lista
                    </ToggleGroupItem>
                    {hasTeam && (
                        <ToggleGroupItem value="tablero" className={VIEW_ITEM}>
                            Tablero
                        </ToggleGroupItem>
                    )}
                    <ToggleGroupItem value="semana" className={VIEW_ITEM}>
                        Semana
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>
        </>
    );
}
