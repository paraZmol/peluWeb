import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
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
        <fieldset className="grid gap-3">
            <legend className="mb-1 text-sm font-medium">
                Además de su agenda, puede…
            </legend>
            {options.map((option) => {
                const id = `${idPrefix}-${option.value}`;

                return (
                    <div key={option.value} className="flex items-center gap-3">
                        <Checkbox
                            id={id}
                            name="permissions[]"
                            value={option.value}
                            defaultChecked={defaultValues.includes(
                                option.value,
                            )}
                        />
                        <Label htmlFor={id} className="font-normal">
                            {option.label}
                        </Label>
                    </div>
                );
            })}
        </fieldset>
    );
}
