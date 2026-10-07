import { Copy, Plus } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { AccessOption, ScheduleRange } from './types';

const COPY_TARGETS = [
    { label: 'A todos los días', weekdays: [1, 2, 3, 4, 5, 6, 7] },
    { label: 'De lunes a viernes', weekdays: [1, 2, 3, 4, 5] },
    { label: 'De lunes a sábado', weekdays: [1, 2, 3, 4, 5, 6] },
];

type Props = {
    label: string;
    ranges: ScheduleRange[];
    accessLevels: AccessOption[];
    canAdd: boolean;
    onAdd: () => void;
    onEdit: (range: ScheduleRange) => void;
    onCopy: (weekdays: number[]) => void;
};

export function DayRow({
    label,
    ranges,
    accessLevels,
    canAdd,
    onAdd,
    onEdit,
    onCopy,
}: Props) {
    const reduceMotion = useReducedMotion();
    const accessLabel = (range: ScheduleRange) =>
        range.accessLevel === 'public'
            ? null
            : accessLevels.find((a) => a.value === range.accessLevel)?.label;

    return (
        <li className="flex flex-col gap-3 p-4">
            <div className="flex min-h-9 items-center justify-between gap-2">
                <h3 className="font-medium">{label}</h3>
                {ranges.length === 0 ? (
                    <span className="text-sm text-muted-foreground">
                        No atiende
                    </span>
                ) : (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-11 md:h-8"
                            >
                                <Copy /> Copiar a…
                                <span className="sr-only">
                                    {' '}
                                    el horario del {label.toLowerCase()}
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {COPY_TARGETS.map((target) => (
                                <DropdownMenuItem
                                    key={target.label}
                                    className="min-h-11"
                                    onSelect={() => onCopy(target.weekdays)}
                                >
                                    {target.label}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>

            <div className="flex flex-wrap gap-2">
                <AnimatePresence initial={false}>
                    {ranges.map((range) => (
                        <motion.button
                            key={`${range.start}-${range.end}`}
                            type="button"
                            layout={!reduceMotion}
                            initial={
                                reduceMotion
                                    ? false
                                    : { opacity: 0, scale: 0.9 }
                            }
                            animate={{ opacity: 1, scale: 1 }}
                            exit={{ opacity: 0 }}
                            transition={{ duration: 0.15 }}
                            onClick={() => onEdit(range)}
                            className={cn(
                                'flex min-h-11 flex-col items-start justify-center rounded-xl border bg-background px-3 py-1 text-left text-sm tabular-nums transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                range.accessLevel !== 'public' &&
                                    'border-primary/50 bg-primary/5',
                            )}
                        >
                            <span className="font-medium">
                                {formatTime(range.start)} –{' '}
                                {formatTime(range.end)}
                            </span>
                            {accessLabel(range) && (
                                <span className="text-xs text-muted-foreground">
                                    {accessLabel(range)}
                                </span>
                            )}
                        </motion.button>
                    ))}
                </AnimatePresence>

                {canAdd && (
                    <Button
                        type="button"
                        variant="outline"
                        className="rounded-xl border-dashed"
                        onClick={onAdd}
                    >
                        <Plus />
                        {ranges.length === 0
                            ? 'Agregar horario'
                            : 'Otro horario'}
                        <span className="sr-only">
                            {' '}
                            el {label.toLowerCase()}
                        </span>
                    </Button>
                )}
            </div>
        </li>
    );
}
