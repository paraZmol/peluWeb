import InputError from '@/components/input-error';
import type { PromotionForm } from '@/hooks/use-promotion-form';
import type { TeamMember } from './types';

type Props = {
    form: PromotionForm;
    team: TeamMember[];
};

/**
 * "Solo con…": the professionals it holds with. None ticked is all of the
 * team; with someone ticked it holds only when the client chooses them.
 */
export function StaffField({ form, team }: Props) {
    const { data, errors } = form;
    const error =
        errors.staff ??
        Object.entries(errors).find(([field]) =>
            field.startsWith('staff.'),
        )?.[1];

    if (team.length === 0) {
        return null;
    }

    return (
        <div
            role="group"
            aria-labelledby="promotion-staff-legend"
            className="grid gap-2"
        >
            <p id="promotion-staff-legend" className="text-sm font-medium">
                Solo con…{' '}
                <span className="font-normal text-muted-foreground">
                    (opcional)
                </span>
            </p>
            <ul className="max-h-60 divide-y overflow-y-auto rounded-xl border bg-card">
                {team.map((person) => (
                    <li key={person.id}>
                        <label className="flex min-h-12 cursor-pointer items-center gap-3 px-3 py-2 text-sm font-medium has-checked:bg-brand-soft has-focus-visible:ring-2 has-focus-visible:ring-ring has-focus-visible:ring-inset">
                            <input
                                type="checkbox"
                                name="staff"
                                value={person.id}
                                checked={data.staff.includes(person.id)}
                                onChange={() => form.toggleStaff(person.id)}
                                className="size-4 shrink-0 accent-primary"
                            />
                            <span className="min-w-0 flex-1 break-words">
                                {person.name}
                            </span>
                        </label>
                    </li>
                ))}
            </ul>
            <p className="text-sm text-muted-foreground">
                {data.staff.length === 0
                    ? 'Sin marcar a nadie, vale con todo el equipo.'
                    : `Vale solo si quien reserva elige a ${data.staff.length === 1 ? 'esa persona' : 'una de estas personas'}; con «Quien esté disponible» no aplica.`}
            </p>
            <InputError message={error} />
        </div>
    );
}
