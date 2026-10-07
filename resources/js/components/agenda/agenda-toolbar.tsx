import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { StaffSelect } from '@/components/booking/staff-select';
import type { StaffOption } from '@/components/booking/types';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDate } from '@/lib/format';
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

export function shiftDate(date: string, days: number): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + days))
        .toISOString()
        .slice(0, 10);
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
                <div className="min-w-0 flex-1 text-center">
                    <h1 className="text-xl font-semibold capitalize">
                        {week
                            ? `${formatDate(week.from)} – ${formatDate(week.to)}`
                            : date === today
                              ? 'Hoy'
                              : formatDate(date)}
                    </h1>
                    {!week && date === today && (
                        <p className="text-sm text-muted-foreground capitalize">
                            {formatDate(date)}
                        </p>
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

            <div className="flex flex-wrap items-center gap-2">
                {canBook && (
                    <Button asChild className="rounded-full">
                        <Link href={create()}>
                            <Plus aria-hidden /> Nueva cita
                        </Link>
                    </Button>
                )}
                {date !== today && (
                    <Button variant="outline" onClick={() => onDate(today)}>
                        Volver a hoy
                    </Button>
                )}
                {hasTeam && (
                    <StaffSelect
                        className="w-auto min-w-44"
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
                    className="ml-auto"
                    aria-label="Vista de la agenda"
                    value={view}
                    onValueChange={(next) => next && onView(next as AgendaView)}
                >
                    <ToggleGroupItem value="lista">Lista</ToggleGroupItem>
                    {hasTeam && (
                        <ToggleGroupItem value="tablero">
                            Tablero
                        </ToggleGroupItem>
                    )}
                    <ToggleGroupItem value="semana">Semana</ToggleGroupItem>
                </ToggleGroup>
            </div>
        </>
    );
}
