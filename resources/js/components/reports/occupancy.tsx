import { formatMinutes } from '@/lib/format';
import { shareOf } from '@/lib/reports';
import { ReportSection, SectionEmpty } from './report-section';
import type { StaffOccupancy } from './types';

const FULL = 100;

/**
 * "62 %"; a dash when there were no hours to fill, and "< 1 %" when the
 * little that was attended would round down to nothing.
 */
function percentLabel(busyMinutes: number, openMinutes: number): string {
    if (openMinutes === 0) {
        return '—';
    }

    const percent = shareOf(busyMinutes, openMinutes);

    return busyMinutes > 0 && percent === 0 ? '< 1 %' : `${percent} %`;
}

function OccupancyRow({ member }: { member: StaffOccupancy }) {
    const percent = shareOf(member.busyMinutes, member.openMinutes);

    return (
        <li
            aria-label={member.name}
            className="flex break-inside-avoid flex-col gap-1"
        >
            <div className="flex items-baseline justify-between gap-3 text-sm">
                {/* The dot hangs before the hours and is clipped when they
                    drop to a line of their own. */}
                <span className="flex min-w-0 flex-wrap gap-x-3 overflow-hidden">
                    <span className="min-w-0 break-words">{member.name}</span>
                    <span className="relative whitespace-nowrap text-muted-foreground tabular-nums before:absolute before:-left-2 before:content-['·']">
                        {formatMinutes(member.busyMinutes)} de{' '}
                        {formatMinutes(member.openMinutes)}
                    </span>
                </span>
                <span className="shrink-0 font-medium tabular-nums">
                    {percentLabel(member.busyMinutes, member.openMinutes)}
                </span>
            </div>
            <div
                aria-hidden
                className="h-2 overflow-hidden rounded-full bg-muted"
            >
                {percent > 0 && (
                    <div
                        className="h-full rounded-full bg-primary"
                        style={{ width: `${Math.min(percent, FULL)}%` }}
                    />
                )}
            </div>
        </li>
    );
}

/** Hours spent attending against the hours each person was there for it. */
export function Occupancy({ staff }: { staff: StaffOccupancy[] }) {
    const busy = staff.reduce((sum, member) => sum + member.busyMinutes, 0);
    const open = staff.reduce((sum, member) => sum + member.openMinutes, 0);

    return (
        <ReportSection
            title="Horas ocupadas"
            description="Tiempo atendiendo frente a las horas de trabajo de cada quien, según su horario actual y sin contar feriados ni ausencias."
        >
            {staff.length === 0 ? (
                <SectionEmpty>
                    Nadie tuvo horario ni citas completadas en este período.
                </SectionEmpty>
            ) : (
                <>
                    <p className="flex flex-col text-sm">
                        <span className="text-2xl font-semibold tabular-nums">
                            {percentLabel(busy, open)}
                        </span>{' '}
                        <span className="text-muted-foreground tabular-nums">
                            del tiempo disponible:{' '}
                            <span className="whitespace-nowrap">
                                {formatMinutes(busy)} de {formatMinutes(open)}
                            </span>
                        </span>
                    </p>
                    <ul className="flex flex-col gap-3">
                        {staff.map((member) => (
                            <OccupancyRow key={member.name} member={member} />
                        ))}
                    </ul>
                </>
            )}
        </ReportSection>
    );
}
