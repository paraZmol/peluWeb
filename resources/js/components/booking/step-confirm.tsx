import { Link, router } from '@inertiajs/react';
import { Wallet } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { BookingWizard } from '@/hooks/use-booking-wizard';
import { flashFailed } from '@/lib/flash';
import { formatMinutes, formatPrice } from '@/lib/format';
import { resume, store } from '@/routes/salons/booking';
import { notice } from '@/routes/verification';
import { BookingSummary } from './booking-summary';
import type { BookableSalon, Quote, Viewer } from './types';

type Props = {
    salon: BookableSalon;
    wizard: BookingWizard;
    quote: Quote | null;
    viewer: Viewer;
};

export function StepConfirm({ salon, wizard, quote, viewer }: Props) {
    const [sending, setSending] = useState(false);
    const staff = salon.staff.find((person) => person.id === wizard.staffId);
    const { slot, date } = wizard;

    if (!slot || !date) {
        return null;
    }

    const confirm = () =>
        router.post(
            store(salon.slug),
            {
                services: wizard.selections.map((selection) => ({
                    id: selection.serviceId,
                    variant: selection.variantId,
                })),
                staff: wizard.staffId,
                start: slot.start,
                note: wizard.note || null,
            },
            {
                preserveState: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
                // The slot was taken meanwhile: back to choosing a time.
                onSuccess: (page) =>
                    flashFailed(page) ? wizard.goTo(2) : wizard.clear(),
                onError: () => wizard.goTo(2),
            },
        );

    return (
        <div className="flex flex-col gap-5">
            <BookingSummary
                date={date}
                time={slot.time}
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

            {quote && quote.depositPercent > 0 && (
                <p className="flex items-start gap-3 rounded-xl bg-primary/10 p-4 text-sm">
                    <Wallet
                        className="mt-0.5 size-5 shrink-0 text-primary"
                        aria-hidden
                    />
                    <span>
                        Para confirmar, el salón pide una seña de{' '}
                        <strong className="whitespace-nowrap tabular-nums">
                            {formatPrice(quote.deposit)}
                        </strong>{' '}
                        ({quote.depositPercent}%). Te diremos cómo enviarla al
                        terminar.
                    </span>
                </p>
            )}

            <div className="grid gap-2">
                <Label htmlFor="booking-note">
                    ¿Algo que debamos saber? (opcional)
                </Label>
                <Textarea
                    id="booking-note"
                    className="min-h-20"
                    maxLength={255}
                    placeholder="Ej. quiero un degradado bajo"
                    value={wizard.note}
                    onChange={(e) => wizard.setNote(e.target.value)}
                />
            </div>

            {viewer.state === 'ready' && (
                <Button
                    size="lg"
                    className="h-12 rounded-full"
                    disabled={sending}
                    onClick={confirm}
                >
                    {sending && <Spinner />}
                    Confirmar reserva
                </Button>
            )}
            {viewer.state === 'guest' && (
                <div className="flex flex-col gap-2">
                    <Button asChild size="lg" className="h-12 rounded-full">
                        {/* A full page load: the server sends guests to sign in and back. */}
                        <a href={resume.url(salon.slug)}>
                            Entrar para confirmar
                        </a>
                    </Button>
                    <p className="text-center text-sm text-muted-foreground">
                        Si no tienes cuenta, podrás crearla ahí mismo. Guardamos
                        lo que elegiste.
                    </p>
                </div>
            )}
            {viewer.state === 'unverified' && (
                <Button asChild size="lg" className="h-12 rounded-full">
                    <Link href={notice()}>
                        Verifica tu correo para confirmar
                    </Link>
                </Button>
            )}
        </div>
    );
}
