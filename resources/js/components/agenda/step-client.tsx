import { router } from '@inertiajs/react';
import { useState } from 'react';
import { BookingSummary } from '@/components/booking/booking-summary';
import { STICKY_ACTION } from '@/components/booking/sticky-action';
import type { BookableSalon, Quote } from '@/components/booking/types';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { flashFailed } from '@/lib/flash';
import { formatMinutes } from '@/lib/format';
import { store } from '@/routes/agenda/bookings';
import type { ClientChoice } from './client-picker';
import { ClientPicker } from './client-picker';

type Props = {
    salon: BookableSalon;
    wizard: BookingWizard;
    quote: Quote | null;
};

/** Last step when the team books: who it is for, and save. */
export function StepClient({ salon, wizard, quote }: Props) {
    const [client, setClient] = useState<ClientChoice>({
        id: null,
        name: '',
        phone: '',
    });
    const [sending, setSending] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const staff = salon.staff.find((person) => person.id === wizard.staffId);
    const { slot, date } = wizard;
    const hasName = client.name.trim().length >= 2;

    if (!slot || !date) {
        return null;
    }

    const save = () =>
        router.post(
            store(),
            {
                services: wizard.selections.map((selection) => ({
                    id: selection.serviceId,
                    variant: selection.variantId,
                })),
                staff: wizard.staffId,
                start: slot.start,
                client_id: client.id,
                client_name: client.id === null ? client.name.trim() : null,
                client_phone:
                    client.id === null ? client.phone.trim() || null : null,
                note: wizard.note || null,
            },
            {
                preserveState: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
                // The time was taken meanwhile: back to choosing one.
                onSuccess: (page) => flashFailed(page) && wizard.slotTaken(),
                onError: (found) =>
                    found.start ? wizard.slotTaken() : setErrors(found),
                onNetworkError: () => {
                    setErrors({
                        network:
                            'No hay conexión. Lo que elegiste sigue aquí: vuelve a tocar "Agendar cita".',
                    });
                },
            },
        );

    return (
        <div className="flex flex-col gap-5">
            <ClientPicker value={client} onChange={setClient} errors={errors} />

            <div className="grid gap-2">
                <Label htmlFor="booking-note">Nota (opcional)</Label>
                <Textarea
                    id="booking-note"
                    className="min-h-20"
                    maxLength={255}
                    placeholder="Ej. llamó por teléfono, quiere degradado bajo"
                    value={wizard.note}
                    onChange={(e) => wizard.setNote(e.target.value)}
                />
            </div>

            <BookingSummary
                date={date}
                time={slot.time}
                timeJustPicked
                staffLabel="Atiende"
                staffName={staff?.name ?? 'Quien esté disponible'}
                duration={
                    quote
                        ? `${quote.minutesVary ? 'Desde ' : ''}${formatMinutes(quote.minutes)}`
                        : undefined
                }
                items={wizard.chosen.map((service) => ({ name: service.name }))}
                total={quote?.price ?? null}
                totalPrefix={quote?.priceVaries ? 'desde' : undefined}
            />

            {(errors.network ?? errors.services ?? errors.note) && (
                <p
                    role="alert"
                    className="rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm"
                >
                    {errors.network ?? errors.services ?? errors.note}
                </p>
            )}

            <div className={STICKY_ACTION}>
                <Button
                    size="lg"
                    className="h-12 w-full rounded-full"
                    disabled={sending || !hasName}
                    onClick={save}
                >
                    {sending && <Spinner />}
                    Agendar cita
                </Button>
                {!hasName && (
                    <p className="mt-1 text-center text-sm text-muted-foreground">
                        Escribe el nombre de tu cliente para agendar.
                    </p>
                )}
            </div>
        </div>
    );
}
