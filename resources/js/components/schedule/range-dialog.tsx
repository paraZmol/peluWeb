import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { overlaps, toMinutes, weekdayLabel } from '@/lib/schedule';
import { AccessPicker } from './access-picker';
import { TimeRangePicker } from './time-range-picker';
import type { AccessOption, ScheduleRange } from './types';

const MIN_RANGE_MINUTES = 15;

type Props = {
    /** The range being edited, or the suggested one when adding. */
    range: ScheduleRange;
    isNew: boolean;
    /** Other ranges of the same day, to avoid overlaps. */
    siblings: ScheduleRange[];
    accessLevels: AccessOption[];
    onSave: (range: ScheduleRange) => void;
    onDelete: () => void;
    onClose: () => void;
};

function problemWith(range: ScheduleRange, siblings: ScheduleRange[]) {
    if (toMinutes(range.end) - toMinutes(range.start) < MIN_RANGE_MINUTES) {
        return 'La salida debe ser al menos 15 minutos después de la entrada.';
    }

    if (siblings.some((other) => overlaps(range, other))) {
        return 'Se cruza con otro horario de este día.';
    }

    return null;
}

export function RangeDialog({
    range,
    isNew,
    siblings,
    accessLevels,
    onSave,
    onDelete,
    onClose,
}: Props) {
    const [draft, setDraft] = useState(range);
    const problem = problemWith(draft, siblings);

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[92svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{weekdayLabel(range.weekday)}</DialogTitle>
                    <DialogDescription>
                        Toca la entrada o la salida y gira la rueda.
                    </DialogDescription>
                </DialogHeader>

                <TimeRangePicker
                    start={draft.start}
                    end={draft.end}
                    onChange={(start, end) =>
                        setDraft({ ...draft, start, end })
                    }
                />
                <p
                    aria-live="polite"
                    className="text-sm text-destructive-foreground empty:hidden"
                >
                    {problem}
                </p>

                <AccessPicker
                    options={accessLevels}
                    value={draft.accessLevel}
                    onChange={(accessLevel) =>
                        setDraft({ ...draft, accessLevel })
                    }
                />

                {/* Sticky: the main action stays reachable on short screens. */}
                <DialogFooter className="sticky bottom-0 gap-2 bg-background pt-2">
                    {!isNew && (
                        <Button
                            type="button"
                            variant="ghost"
                            className="text-destructive-foreground sm:mr-auto"
                            onClick={onDelete}
                        >
                            Quitar este horario
                        </Button>
                    )}
                    <Button
                        type="button"
                        className="rounded-full"
                        disabled={problem !== null}
                        onClick={() => onSave(draft)}
                    >
                        Listo
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
