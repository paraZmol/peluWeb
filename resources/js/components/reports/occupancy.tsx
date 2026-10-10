import { formatMinutes } from '@/lib/format';
import { shareOf } from '@/lib/reports';
import { ReportSection, SectionEmpty } from './report-section';
import type { StaffOccupancy } from './types';

const FULL = 100;

/** "62 %", or a dash when the person had no hours to fill. */
const percentOf = (member: StaffOccupancy) =>
    member.openMinutes === 0
        ? '—'
        : `${shareOf(member.busyMinutes, member.openMinutes)} %`;

function OccupancyRow({ member }: { member: StaffOccupancy }) {
    const percent = shareOf(member.busyMinutes, member.openMinutes);

    return (
        <li
            aria-label={member.name}
            className="flex break-inside-avoid flex-col gap-1"
        >
            <div className="flex items-baseline justify-between gap-3 text-sm">
                <span className="min-w-0 break-words">
                    {member.name}
                    <span className="text-muted-foreground">
                        {' · '}
                        {formatMinutes(member.busyMinutes)} de{' '}
                        {formatMinutes(member.openMinutes)}
                    </span>
                </span>
                <span className="shrink-0 font-medium tabular-nums">
                    {percentOf(member)}
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
                    <p className="text-sm">
                        <span className="text-2xl font-semibold tabular-nums">
                            {open === 0 ? '—' : `${shareOf(busy, open)} %`}
                        </span>{' '}
                        <span className="text-muted-foreground">
                            del tiempo disponible: {formatMinutes(busy)} de{' '}
                            {formatMinutes(open)}
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
