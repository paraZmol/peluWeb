import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { StaffOption } from './types';

const EVERYONE = 'all';

type Props = {
    options: StaffOption[];
    /** null = the whole team (only offered with `everyoneLabel`). */
    value: number | null;
    onChange: (staffId: number | null) => void;
    /** Adds a first option for nobody in particular. */
    everyoneLabel?: string;
    /** Shown before each name, e.g. "Con". */
    prefix?: string;
    className?: string;
};

/** Dropdown to pick one professional of the team. */
export function StaffSelect({
    options,
    value,
    onChange,
    everyoneLabel,
    prefix,
    className,
}: Props) {
    return (
        <Select
            value={value?.toString() ?? EVERYONE}
            onValueChange={(next) =>
                onChange(next === EVERYONE ? null : Number(next))
            }
        >
            <SelectTrigger className={className} aria-label="Profesional">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {everyoneLabel && (
                    <SelectItem value={EVERYONE}>{everyoneLabel}</SelectItem>
                )}
                {options.map((staff) => (
                    <SelectItem key={staff.id} value={staff.id.toString()}>
                        {prefix ? `${prefix} ${staff.name}` : staff.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
