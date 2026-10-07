import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { StaffServicesForm } from '@/components/schedule/staff-services-form';
import { TimeOffSection } from '@/components/schedule/time-off-section';
import type { AccessOption, StaffEditor } from '@/components/schedule/types';
import { WeeklyScheduleEditor } from '@/components/schedule/weekly-schedule-editor';
import { StaffHeader } from '@/components/staff/staff-header';
import { cn } from '@/lib/utils';
import { index } from '@/routes/staff';
import { mine } from '@/routes/staff/schedule';
import type { Option } from '@/types';

type Props = {
    staff: StaffEditor;
    accessLevels: AccessOption[];
    timeOffReasons: Option[];
    canManageTeam: boolean;
    canSetPrices: boolean;
    today: string;
};

const TABS = [
    { key: 'hours', label: 'Horario' },
    { key: 'time-off', label: 'Ausencias' },
    { key: 'services', label: 'Servicios' },
] as const;

type TabKey = (typeof TABS)[number]['key'];

export default function StaffSchedule({
    staff,
    accessLevels,
    timeOffReasons,
    canManageTeam,
    canSetPrices,
    today,
}: Props) {
    const [tab, setTab] = useState<TabKey>('hours');

    // Panels stay mounted so unsaved hours survive a tab change.
    const panel = (key: TabKey, children: ReactNode) => (
        <div
            role="tabpanel"
            id={`panel-${key}`}
            aria-labelledby={`tab-${key}`}
            hidden={tab !== key}
        >
            {children}
        </div>
    );

    return (
        <>
            <Head title={`Horario de ${staff.name}`} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6">
                {canManageTeam && (
                    <Link
                        href={index()}
                        className="flex min-h-11 items-center gap-1 self-start text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ChevronLeft className="size-4" /> Profesionales
                    </Link>
                )}

                <StaffHeader staff={staff} />

                <div
                    role="tablist"
                    aria-label="Secciones"
                    className="grid grid-cols-3 gap-1 rounded-full border bg-card p-1"
                >
                    {TABS.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            role="tab"
                            id={`tab-${item.key}`}
                            aria-selected={tab === item.key}
                            aria-controls={`panel-${item.key}`}
                            onClick={() => setTab(item.key)}
                            className={cn(
                                'min-h-11 rounded-full px-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                tab === item.key
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-accent',
                            )}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                {panel(
                    'hours',
                    <WeeklyScheduleEditor
                        key={staff.id}
                        staffId={staff.id}
                        saved={staff.ranges}
                        accessLevels={accessLevels}
                    />,
                )}
                {panel(
                    'time-off',
                    <TimeOffSection
                        staffId={staff.id}
                        items={staff.timeOff}
                        reasons={timeOffReasons}
                        today={today}
                    />,
                )}
                {panel(
                    'services',
                    <StaffServicesForm
                        key={staff.id}
                        staffId={staff.id}
                        services={staff.services}
                        canSetPrices={canSetPrices}
                    />,
                )}
            </div>
        </>
    );
}

StaffSchedule.layout = {
    breadcrumbs: [{ title: 'Horario', href: mine() }],
};
