import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { markBookingCelebration } from '@/lib/celebration';
import { withCompanion } from '@/lib/companion';
import { flashFailed, flashMessage } from '@/lib/flash';
import { formatMinutes } from '@/lib/format';
import { promotedTotal } from '@/lib/promo-offers';
import { store } from '@/routes/salons/booking';
import { BookingSummary } from './booking-summary';
import { ConfirmAction } from './confirm-action';
import { DepositNotice } from './deposit-notice';
import type { BookableSalon, Quote, Viewer } from './types';

type Props = {
    salon: BookableSalon;
    wizard: BookingWizard;
    quote: Quote | null;
    viewer: Viewer;
};

export function StepConfirm({ salon, wizard, quote, viewer }: Props) {
    const [sending, setSending] = useState(false);
    const [problem, setProblem] = useState<string>();
    const staff = salon.staff.find((person) => person.id === wizard.staffId);
    const { slot, date } = wizard;

    if (!slot || !date) {
        return null;
    }

    // The chosen time says whether a promotion applies and what it saves.
    const money = quote ? promotedTotal(quote, slot.promotion) : null;

    const confirm = () =>
        router.post(
            store(salon.slug),
            {
                services: wizard.lines.map((selection) => ({
                    id: selection.serviceId,
                    variant: selection.variantId,
                    companion: selection.companion ?? null,
                })),
                staff: wizard.staffId,
                start: slot.start,
                note: wizard.note || null,
                // The price on this screen has a promotion in it: the server
                // refuses rather than book at another one.
                expects_promotion: slot.promotion !== null,
            },
            {
                preserveState: true,
                onStart: () => {
                    setSending(true);
                    setProblem(undefined);
                },
                onFinish: () => setSending(false),
                onSuccess: (page) => {
                    // A business rule said no. Only "that time was taken"
                    // sends back to the times; the rest is read right here.
                    if (flashFailed(page)) {
                        return flashMessage(page)?.includes('horario')
                            ? wizard.slotTaken()
                            : setProblem(flashMessage(page));
                    }

                    // Its page greets it with confetti, once.
                    markBookingCelebration(page.props.booking);
                    wizard.clear();
                },
                onError: (errors) =>
                    errors.start
                        ? wizard.slotTaken()
                        : setProblem(Object.values(errors)[0]),
                onNetworkError: () => {
                    setProblem(
                        'No hay conexión. Lo que elegiste sigue aquí: vuelve a tocar "Confirmar reserva".',
                    );
                },
            },
        );

    return (
        <div className="flex flex-col gap-5">
            <BookingSummary
                date={date}
                time={slot.time}
                timeJustPicked
                staffName={staff?.name ?? 'Quien esté disponible'}
                duration={
                    quote
                        ? `${quote.minutesVary ? 'Desde ' : ''}${formatMinutes(quote.minutes)}`
                        : undefined
                }
                items={withCompanion(wizard.chosen, wizard.companion)}
                footer={
                    wizard.companion && (
                        <p className="text-sm text-muted-foreground">
                            Les atiende {staff?.name ?? 'la misma persona'}, uno
                            después del otro.
                        </p>
                    )
                }
                total={money?.total ?? null}
                totalPrefix={quote?.priceVaries ? 'desde' : undefined}
                listTotal={money?.listTotal}
                promotion={money?.promotion}
            />

            {quote && money && quote.depositPercent > 0 && (
                <DepositNotice
                    amount={money.deposit}
                    percent={quote.depositPercent}
                />
            )}

            <div className="grid gap-2">
                <Label htmlFor="booking-note">
                    ¿Algo que debamos saber? (opcional)
                </Label>
                <Textarea
                    id="booking-note"
                    className="min-h-20"
                    maxLength={255}
                    placeholder="Ej. tengo el cuero cabelludo sensible"
                    value={wizard.note}
                    onChange={(e) => wizard.setNote(e.target.value)}
                />
            </div>

            {problem && (
                <p
                    role="alert"
                    className="rounded-xl border border-destructive/40 bg-destructive/10 p-4 text-sm"
                >
                    {problem}
                </p>
            )}

            <ConfirmAction
                viewer={viewer}
                salonSlug={salon.slug}
                sending={sending}
                onConfirm={confirm}
            />
        </div>
    );
}
