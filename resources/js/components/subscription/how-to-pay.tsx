import { Smartphone } from 'lucide-react';
import type { Subscription } from './types';

type Props = { payTo: Subscription['payTo'] };

/** Where to send the money; the platform records it and the plan runs on. */
export function HowToPay({ payTo }: Props) {
    return (
        <section
            aria-labelledby="how-to-pay-title"
            className="flex flex-col gap-4 rounded-xl border bg-card p-5 shadow-card"
        >
            <h2 id="how-to-pay-title" className="text-lg font-semibold">
                Cómo pagar
            </h2>

            {payTo.yape ? (
                <div className="flex items-center gap-3 rounded-lg bg-brand-soft p-4">
                    <Smartphone
                        className="size-5 shrink-0 text-brand-text"
                        aria-hidden
                    />
                    <div className="min-w-0">
                        <p className="text-sm text-muted-foreground">
                            Yapea al número
                        </p>
                        <p className="text-xl font-semibold tracking-wide tabular-nums">
                            {payTo.yape}
                        </p>
                        {payTo.name && (
                            <p className="text-sm break-words">
                                a nombre de {payTo.name}
                            </p>
                        )}
                    </div>
                </div>
            ) : (
                <p className="rounded-lg bg-muted p-4 text-sm">
                    Te enviaremos por WhatsApp los datos para pagar por Yape o
                    transferencia.
                </p>
            )}

            <ol className="flex list-inside list-decimal flex-col gap-2 text-sm marker:text-muted-foreground">
                <li>Paga el monto de tu plan.</li>
                <li>
                    Registramos tu pago y tu plan se extiende: si el salón
                    estaba en pausa, se reactiva al momento.
                </li>
                <li>Lo verás aquí, en «Pagos», con los días que cubre.</li>
            </ol>
        </section>
    );
}
