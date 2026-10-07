import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    /** "Y-m-d" on the salon's clock. */
    today: string;
    from: string;
    to: string;
    onChange: (from: string, to: string) => void;
    error?: string;
};

function addDays(date: string, days: number): string {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + days))
        .toISOString()
        .slice(0, 10);
}

/** From/to dates with one-tap shortcuts for the usual cases. */
export function DateRangeFields({ today, from, to, onChange, error }: Props) {
    const shortcuts = [
        { label: 'Hoy', from: today, to: today },
        { label: 'Mañana', from: addDays(today, 1), to: addDays(today, 1) },
        { label: 'Una semana', from: today, to: addDays(today, 6) },
    ];

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap gap-2">
                {shortcuts.map((shortcut) => (
                    <Button
                        key={shortcut.label}
                        type="button"
                        size="sm"
                        variant={
                            from === shortcut.from && to === shortcut.to
                                ? 'default'
                                : 'outline'
                        }
                        className="rounded-full"
                        onClick={() => onChange(shortcut.from, shortcut.to)}
                    >
                        {shortcut.label}
                    </Button>
                ))}
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div className="grid gap-2">
                    <Label htmlFor="range-from">Desde</Label>
                    <Input
                        id="range-from"
                        type="date"
                        min={today}
                        value={from}
                        onChange={(e) =>
                            onChange(
                                e.target.value,
                                to < e.target.value ? e.target.value : to,
                            )
                        }
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="range-to">Hasta</Label>
                    <Input
                        id="range-to"
                        type="date"
                        min={from}
                        value={to}
                        onChange={(e) => onChange(from, e.target.value)}
                    />
                </div>
            </div>
            <InputError message={error} />
        </div>
    );
}
