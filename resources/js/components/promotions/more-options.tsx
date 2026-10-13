import { ChevronDown } from 'lucide-react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PromotionForm } from '@/hooks/use-promotion-form';
import { WhenFields } from './when-fields';

export const MAX_NAME = 120;

type Props = {
    form: PromotionForm;
    /** Days and hours already sit in the form itself ("Horas flojas"). */
    hidesWhen: boolean;
};

/** What most promotions leave as it comes: folded until someone asks. */
export function MoreOptions({ form, hidesWhen }: Props) {
    const { data, setData, errors } = form;
    // An error inside must be seen: it opens unfolded.
    const hasError = ['weekdays', 'from_time', 'to_time', 'quota', 'name'].some(
        (field) => errors[field],
    );

    return (
        <Collapsible
            defaultOpen={hasError}
            key={String(hasError)}
            className="rounded-xl border"
        >
            <CollapsibleTrigger className="group flex min-h-12 w-full items-center justify-between gap-3 rounded-xl px-4 text-left text-sm font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50">
                Más opciones
                <ChevronDown
                    className="size-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none"
                    aria-hidden
                />
            </CollapsibleTrigger>
            <CollapsibleContent className="grid gap-5 border-t p-4">
                {!hidesWhen && <WhenFields form={form} required={false} />}

                <div className="grid gap-2">
                    <Label htmlFor="promotion-quota">
                        Cupos{' '}
                        <span className="font-normal text-muted-foreground">
                            (opcional)
                        </span>
                    </Label>
                    <Input
                        id="promotion-quota"
                        type="text"
                        inputMode="numeric"
                        autoComplete="off"
                        placeholder="Sin límite"
                        value={data.quota}
                        onChange={(event) =>
                            setData(
                                'quota',
                                event.target.value.replace(/\D/g, ''),
                            )
                        }
                        aria-invalid={Boolean(errors.quota)}
                        className="h-11 w-40 tabular-nums"
                    />
                    <p className="text-sm text-muted-foreground">
                        Cuántas reservas pueden usarla. Una reserva cancelada
                        devuelve su cupo.
                    </p>
                    <InputError message={errors.quota} />
                </div>

                <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm">
                    <Checkbox
                        checked={data.once_per_client}
                        onCheckedChange={(isChecked) =>
                            setData('once_per_client', isChecked === true)
                        }
                    />
                    Una por cliente
                </label>

                <div className="grid gap-2">
                    <Label htmlFor="promotion-name">
                        Título{' '}
                        <span className="font-normal text-muted-foreground">
                            (opcional)
                        </span>
                    </Label>
                    <Input
                        id="promotion-name"
                        value={data.name}
                        maxLength={MAX_NAME}
                        placeholder={
                            form.writtenTitle ?? 'Lo escribimos por ti'
                        }
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                        aria-invalid={Boolean(errors.name)}
                        className="h-11"
                    />
                    <p className="text-sm text-muted-foreground">
                        Si lo dejas vacío, lo escribimos con lo que ofrece.
                    </p>
                    <InputError message={errors.name} />
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
