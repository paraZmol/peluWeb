import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDate } from '@/lib/format';
import { destroy, store } from '@/routes/holidays';
import type { Holiday } from './types';

type Props = {
    holidays: Holiday[];
    /** "Y-m-d" on the salon's clock. */
    today: string;
};

/** Days the whole salon closes (feriados, mantenimiento, inventario). */
export function HolidaysSection({ holidays, today }: Props) {
    return (
        <section
            aria-labelledby="holidays-title"
            className="flex flex-col gap-4 rounded-xl border bg-card p-5"
        >
            <div>
                <h2 id="holidays-title" className="text-lg font-semibold">
                    Días que el salón cierra
                </h2>
                <p className="text-sm text-muted-foreground">
                    Feriados o cierres de todo el salón. Esos días nadie podrá
                    reservar.
                </p>
            </div>

            {holidays.length > 0 && (
                <ul className="divide-y rounded-xl border">
                    {holidays.map((holiday) => (
                        <li
                            key={holiday.id}
                            className="flex items-center gap-3 px-4 py-2"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="font-medium tabular-nums">
                                    {formatDate(holiday.date)}
                                </p>
                                <p className="text-sm break-words text-muted-foreground">
                                    {holiday.name}
                                </p>
                            </div>
                            <ConfirmRemoveDialog
                                triggerLabel="Eliminar"
                                title="¿Eliminar este día de cierre?"
                                description="El salón volverá a atender ese día con el horario normal."
                                confirmLabel="Sí, eliminar"
                                form={destroy.form(holiday.id)}
                            />
                        </li>
                    ))}
                </ul>
            )}

            <Form
                {...store.form()}
                resetOnSuccess
                options={{ preserveScroll: true }}
                className="grid gap-3 sm:grid-cols-[auto_1fr_auto] sm:items-end"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="holiday-date">Fecha</Label>
                            <Input
                                id="holiday-date"
                                name="date"
                                type="date"
                                min={today}
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="holiday-name">Motivo</Label>
                            <Input
                                id="holiday-name"
                                name="name"
                                maxLength={80}
                                required
                                placeholder="Ej. Navidad"
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Agregar
                        </Button>
                        <InputError
                            className="sm:col-span-3"
                            message={errors.date ?? errors.name}
                        />
                    </>
                )}
            </Form>
        </section>
    );
}
