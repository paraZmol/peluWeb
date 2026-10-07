import { router } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    MAX_RANGES_PER_DAY,
    useWeeklySchedule,
} from '@/hooks/use-weekly-schedule';
import { WEEKDAYS } from '@/lib/schedule';
import { cn } from '@/lib/utils';
import { update } from '@/routes/staff/schedule';
import { DayRow } from './day-row';
import { RangeDialog } from './range-dialog';
import type { AccessOption, ScheduleRange } from './types';

type Props = {
    staffId: number;
    saved: ScheduleRange[];
    accessLevels: AccessOption[];
};

type Editing = { range: ScheduleRange; original: ScheduleRange | null };

export function WeeklyScheduleEditor({ staffId, saved, accessLevels }: Props) {
    const week = useWeeklySchedule(saved);
    const reduceMotion = useReducedMotion();
    const [editing, setEditing] = useState<Editing | null>(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string>();
    const { isDirty } = week;

    // Leaving with unsaved hours is the easiest way to lose work here.
    useEffect(() => {
        if (!isDirty) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);
        const stopListening = router.on('before', (event) =>
            event.detail.visit.method === 'get'
                ? window.confirm(
                      'Tienes cambios en el horario sin guardar. ¿Salir sin guardar?',
                  )
                : true,
        );

        return () => {
            window.removeEventListener('beforeunload', warn);
            stopListening();
        };
    }, [isDirty]);

    const save = () =>
        router.put(
            update(staffId),
            {
                ranges: week.ranges.map((range) => ({
                    weekday: range.weekday,
                    start: range.start,
                    end: range.end,
                    access_level: range.accessLevel,
                })),
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () => setError(undefined),
                onError: (errors) => setError(Object.values(errors)[0]),
            },
        );

    const startAdding = (weekday: number) => {
        const range = week.suggest(weekday);

        if (range) {
            setEditing({ range, original: null });
        }
    };

    return (
        <div className="flex flex-col gap-4">
            <ul className="divide-y rounded-xl border bg-card">
                {WEEKDAYS.map((day) => (
                    <DayRow
                        key={day.iso}
                        label={day.label}
                        ranges={week.ofDay(day.iso)}
                        accessLevels={accessLevels}
                        canAdd={
                            week.ofDay(day.iso).length < MAX_RANGES_PER_DAY &&
                            week.suggest(day.iso) !== null
                        }
                        onAdd={() => startAdding(day.iso)}
                        onEdit={(range) =>
                            setEditing({ range, original: range })
                        }
                        onCopy={(weekdays) => week.copyDay(day.iso, weekdays)}
                    />
                ))}
            </ul>

            <AnimatePresence>
                {week.isDirty && (
                    <motion.div
                        initial={reduceMotion ? false : { opacity: 0, y: 12 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.2, ease: [0.22, 1, 0.36, 1] }}
                        className="sticky bottom-[calc(1rem+env(safe-area-inset-bottom))] z-10 flex flex-col gap-2 rounded-2xl border bg-card p-3 shadow-lg sm:flex-row sm:items-center"
                    >
                        <p
                            role={error ? 'alert' : 'status'}
                            className={cn(
                                'flex-1 px-1 text-sm',
                                error
                                    ? 'text-destructive-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {error ?? 'Tienes cambios sin guardar.'}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={saving}
                                onClick={week.reset}
                            >
                                Descartar
                            </Button>
                            <Button
                                type="button"
                                className="flex-1 rounded-full sm:flex-none"
                                disabled={saving}
                                onClick={save}
                            >
                                {saving && <Spinner />}
                                Guardar horario
                            </Button>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            {editing && (
                <RangeDialog
                    range={editing.range}
                    isNew={editing.original === null}
                    siblings={week
                        .ofDay(editing.range.weekday)
                        .filter((range) => range !== editing.original)}
                    accessLevels={accessLevels}
                    onSave={(range) => {
                        week.replace(editing.original, range);
                        setEditing(null);
                    }}
                    onDelete={() => {
                        if (editing.original) {
                            week.remove(editing.original);
                        }

                        setEditing(null);
                    }}
                    onClose={() => setEditing(null)}
                />
            )}
        </div>
    );
}
