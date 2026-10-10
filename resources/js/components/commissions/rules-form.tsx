import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useCommissionRulesForm } from '@/hooks/use-commission-rules-form';
import { PercentInput } from './percent-input';
import { ServiceRuleField } from './service-rule-field';
import type { StaffCommission } from './types';

type Props = { staff: StaffCommission; onSaved: () => void };

/** The general percent and what each service pays instead, if anything. */
export function RulesForm({ staff, onSaved }: Props) {
    const form = useCommissionRulesForm(staff, onSaved);

    return (
        <form onSubmit={form.submit} noValidate className="flex flex-col gap-5">
            <div className="grid gap-2">
                <Label htmlFor="rules-percent">Porcentaje general</Label>
                <div className="sm:w-40">
                    <PercentInput
                        id="rules-percent"
                        value={form.data.percent}
                        onChange={form.setPercent}
                        aria-invalid={Boolean(form.errors.percent)}
                        aria-describedby="rules-percent-hint rules-percent-error"
                        className="h-12 text-lg"
                    />
                </div>
                <p
                    id="rules-percent-hint"
                    className="text-sm text-muted-foreground"
                >
                    Lo que gana de cada servicio que atiende, de 0 a 100. La
                    propina no entra: es de quien atendió.
                </p>
                <InputError
                    id="rules-percent-error"
                    message={form.errors.percent}
                />
            </div>

            <section
                aria-labelledby="rules-services-title"
                className="flex flex-col gap-1"
            >
                <h3 id="rules-services-title" className="text-sm font-medium">
                    Por servicio
                </h3>
                {staff.services.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                        {staff.name} todavía no tiene servicios asignados.
                        Cuando los tenga, aquí podrás darle a cada uno otro
                        porcentaje o un monto fijo.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {staff.services.map((service, index) => (
                            <ServiceRuleField
                                key={service.id}
                                name={service.name}
                                rule={form.data.services[index]}
                                onChange={(patch) =>
                                    form.setService(index, patch)
                                }
                                error={form.serviceError(index)}
                            />
                        ))}
                    </ul>
                )}
            </section>

            <p className="rounded-xl bg-muted p-3 text-sm text-muted-foreground">
                Los cambios valen para las citas que se completen desde ahora;
                las ya completadas conservan su comisión.
            </p>

            <DialogFooter className="gap-2 [&_button]:h-12 sm:[&_button]:h-10">
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Volver
                    </Button>
                </DialogClose>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Guardar reglas
                </Button>
            </DialogFooter>
        </form>
    );
}
