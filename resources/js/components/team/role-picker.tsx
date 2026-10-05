import { cn } from '@/lib/utils';
import type { Option, SalonRole } from '@/types';

type Props = {
    options: Option<SalonRole>[];
    value: SalonRole;
    onChange: (role: SalonRole) => void;
};

const hints: Record<SalonRole, string> = {
    owner: 'Acceso total, incluida la caja y el equipo.',
    staff: 'Atiende y gestiona su propia agenda.',
};

/**
 * Controlled because the permission list depends on the chosen role.
 * Still posts a native `role` field so it works inside Inertia <Form>.
 */
export function RolePicker({ options, value, onChange }: Props) {
    return (
        <div role="radiogroup" className="grid gap-3 sm:grid-cols-2">
            {options.map((option) => (
                <label key={option.value} className="cursor-pointer">
                    <input
                        type="radio"
                        name="role"
                        value={option.value}
                        checked={value === option.value}
                        onChange={() => onChange(option.value)}
                        className="peer sr-only"
                    />
                    <span
                        className={cn(
                            'flex h-full flex-col gap-1 rounded-xl border bg-card p-4 transition-colors',
                            'peer-checked:border-primary peer-checked:bg-primary/10 peer-focus-visible:ring-2 peer-focus-visible:ring-ring',
                        )}
                    >
                        <span className="font-medium">{option.label}</span>
                        <span className="text-xs text-muted-foreground">
                            {hints[option.value]}
                        </span>
                    </span>
                </label>
            ))}
        </div>
    );
}
