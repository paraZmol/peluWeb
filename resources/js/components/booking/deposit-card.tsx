import { MessageCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { formatPrice } from '@/lib/format';
import { PRIMARY_ACTION } from '@/lib/skin-actions';
import type { ClientBooking } from './types';

type Props = { deposit: NonNullable<ClientBooking['deposit']> };

const MINUTE = 60_000;

function minutesLeft(expiresAt: string): number {
    return Math.max(
        0,
        Math.ceil((new Date(expiresAt).getTime() - Date.now()) / MINUTE),
    );
}

/** How to send the deposit, and how long the slot is being held. */
export function DepositCard({ deposit }: Props) {
    const [left, setLeft] = useState(() =>
        deposit.expiresAt ? minutesLeft(deposit.expiresAt) : null,
    );

    useEffect(() => {
        if (!deposit.expiresAt) {
            return;
        }

        const expiresAt = deposit.expiresAt;
        const timer = window.setInterval(
            () => setLeft(minutesLeft(expiresAt)),
            MINUTE / 2,
        );

        return () => window.clearInterval(timer);
    }, [deposit.expiresAt]);

    if (deposit.isPaid) {
        return (
            <p className="rounded-xl p-4 text-sm skin-card">
                Seña recibida:{' '}
                <strong className="tabular-nums">
                    {formatPrice(deposit.amount)}
                </strong>
                {deposit.isRefundable === false &&
                    ' · no reembolsable por cancelar fuera de plazo'}
                {deposit.isRefundable === true &&
                    ' · el salón coordinará contigo la devolución'}
            </p>
        );
    }

    if (left === null) {
        return null;
    }

    return (
        <section
            aria-labelledby="deposit-title"
            className="flex flex-col gap-3 rounded-xl border border-brand-line bg-brand-soft p-5"
        >
            <h2 id="deposit-title" className="font-sans text-lg font-bold">
                Falta la seña de{' '}
                <span className="tabular-nums">
                    {formatPrice(deposit.amount)}
                </span>
            </h2>
            <p className="text-sm" role="status">
                Guardamos tu horario{' '}
                <strong className="tabular-nums">
                    {left === 1 ? '1 minuto' : `${left} minutos`}
                </strong>{' '}
                más. Envía el comprobante por WhatsApp y el salón confirmará tu
                reserva.
            </p>
            {deposit.instructions && (
                <p className="rounded-lg bg-card p-3 text-sm break-words whitespace-pre-line">
                    {deposit.instructions}
                </p>
            )}
            {deposit.whatsappUrl && (
                <a
                    href={deposit.whatsappUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={PRIMARY_ACTION}
                >
                    <MessageCircle aria-hidden /> Enviar comprobante por
                    WhatsApp
                </a>
            )}
        </section>
    );
}
