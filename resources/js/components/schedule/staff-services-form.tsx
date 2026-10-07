import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatMinutes, formatPrice } from '@/lib/format';
import { update } from '@/routes/staff/services';
import type { StaffServiceItem } from './types';

type Props = {
    staffId: number;
    services: StaffServiceItem[];
    canSetPrices: boolean;
};

type Draft = { assigned: boolean; active_minutes: string; price: string };

export function StaffServicesForm({ staffId, services, canSetPrices }: Props) {
    const form = useForm({
        drafts: Object.fromEntries(
            services.map((service): [number, Draft] => [
                service.id,
                {
                    assigned: service.assigned,
                    active_minutes: service.activeMinutes?.toString() ?? '',
                    price: service.price ?? '',
                },
            ]),
        ),
    });
    const { drafts } = form.data;
    const change = (id: number, patch: Partial<Draft>) =>
        form.setData('drafts', {
            ...drafts,
            [id]: { ...drafts[id], ...patch },
        });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform(() => ({
            services: services
                .filter((service) => drafts[service.id].assigned)
                .map((service) => ({
                    service_id: service.id,
                    active_minutes: drafts[service.id].active_minutes || null,
                    price: drafts[service.id].price || null,
                })),
        }));
        form.submit(update(staffId), { preserveScroll: true });
    };

    if (services.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                El salón aún no tiene servicios.
            </p>
        );
    }

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-4">
            <p className="text-sm text-muted-foreground">
                Marca lo que hace esta persona. Deja el tiempo
                {canSetPrices && ' y el precio'} en blanco para usar los del
                salón.
            </p>
            <ul className="divide-y rounded-xl border bg-card">
                {services.map((service) => {
                    const draft = drafts[service.id];

                    return (
                        <li
                            key={service.id}
                            className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center"
                        >
                            <label className="flex min-h-11 min-w-0 flex-1 cursor-pointer items-center gap-3">
                                <Checkbox
                                    checked={draft.assigned}
                                    onCheckedChange={(checked) =>
                                        change(service.id, {
                                            assigned: checked === true,
                                        })
                                    }
                                />
                                <span className="min-w-0">
                                    <span className="block font-medium break-words">
                                        {service.name}
                                    </span>
                                    <span className="block text-sm text-muted-foreground tabular-nums">
                                        {formatMinutes(service.baseMinutes)} ·{' '}
                                        {formatPrice(service.basePrice)}
                                        {!service.isActive && ' · en pausa'}
                                    </span>
                                </span>
                            </label>

                            {draft.assigned && (
                                <div className="flex gap-2 pl-7 sm:pl-0">
                                    <Input
                                        type="number"
                                        inputMode="numeric"
                                        min={5}
                                        step={5}
                                        className="w-24"
                                        aria-label={`Minutos que le toma ${service.name}`}
                                        placeholder={`${service.baseMinutes} min`}
                                        value={draft.active_minutes}
                                        onChange={(e) =>
                                            change(service.id, {
                                                active_minutes: e.target.value,
                                            })
                                        }
                                    />
                                    {canSetPrices && (
                                        <Input
                                            inputMode="decimal"
                                            className="w-24"
                                            aria-label={`Precio de ${service.name} con esta persona`}
                                            placeholder={`S/ ${service.basePrice}`}
                                            value={draft.price}
                                            onChange={(e) =>
                                                change(service.id, {
                                                    price: e.target.value,
                                                })
                                            }
                                        />
                                    )}
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>
            <InputError message={Object.values(form.errors)[0]} />
            <Button
                type="submit"
                className="rounded-full sm:self-end"
                disabled={form.processing}
            >
                {form.processing && <Spinner />}
                Guardar servicios
            </Button>
        </form>
    );
}
