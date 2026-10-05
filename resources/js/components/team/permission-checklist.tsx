import { Checkbox } from '@/components/ui/checkbox';
import type { Option, SalonPermission } from '@/types';

type Props = {
    options: Option<SalonPermission>[];
    defaultValues?: SalonPermission[];
    idPrefix: string;
};

export function PermissionChecklist({
    options,
    defaultValues = [],
    idPrefix,
}: Props) {
    return (
        <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">
                Además de su agenda, puede…
            </legend>
            {options.map((option) => {
                const id = `${idPrefix}-${option.value}`;

                return (
                    <label
                        key={option.value}
                        htmlFor={id}
                        className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border bg-card p-3 hover:bg-accent"
                    >
                        <Checkbox
                            id={id}
                            name="permissions[]"
                            value={option.value}
                            defaultChecked={defaultValues.includes(
                                option.value,
                            )}
                        />
                        <span className="text-sm">{option.label}</span>
                    </label>
                );
            })}
        </fieldset>
    );
}
