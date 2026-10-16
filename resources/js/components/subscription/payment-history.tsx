import { ReceiptText } from 'lucide-react';
import { CashFigure } from '@/components/cash/cash-figure';
import { formatCents, formatDate } from '@/lib/format';
import { formatPlanDate, monthsText } from '@/lib/subscription';
import type { SubscriptionPayment } from './types';

type Props = { payments: SubscriptionPayment[] };

/** What the salon already paid, newest first, and the days each one covers. */
export function PaymentHistory({ payments }: Props) {
    return (
        <section
            aria-labelledby="payments-title"
            className="flex flex-col gap-2"
        >
            <h2 id="payments-title" className="px-1 text-lg font-semibold">
                Pagos
            </h2>
            {payments.length === 0 ? (
                <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-6 text-center">
                    <ReceiptText
                        className="size-6 text-muted-foreground"
                        aria-hidden
                    />
                    <p className="text-sm text-muted-foreground">
                        Aún no hay pagos. Cuando registremos el primero, aquí
                        verás cuánto pagaste, cómo y qué días cubre.
                    </p>
                    <a
                        href="#how-to-pay-title"
                        className="text-sm font-medium text-brand-text underline underline-offset-4"
                    >
                        Ver cómo pagar
                    </a>
                </div>
            ) : (
                <ul className="divide-y rounded-xl border bg-card">
                    {payments.map((payment) => (
                        <li key={payment.id} className="p-4">
                            <dl className="grid grid-cols-3 gap-x-4 gap-y-2 sm:grid-cols-[minmax(0,1fr)_5.5rem_7rem_5.5rem] sm:items-center sm:gap-x-3">
                                <div className="col-span-3 min-w-0 sm:col-span-1">
                                    <dt className="sr-only">Plan</dt>
                                    <dd className="font-medium">
                                        Plan {payment.planName} ·{' '}
                                        {monthsText(payment.months)}
                                    </dd>
                                    <dt className="sr-only">Cubre</dt>
                                    <dd className="text-sm text-muted-foreground">
                                        Del {formatPlanDate(payment.coversFrom)}{' '}
                                        al {formatPlanDate(payment.coversUntil)}
                                    </dd>
                                </div>
                                <CashFigure label="Monto">
                                    {formatCents(payment.amountCents)}
                                </CashFigure>
                                <CashFigure label="Medio">
                                    {payment.method}
                                </CashFigure>
                                <CashFigure label="Pagado">
                                    {payment.paidOn
                                        ? formatDate(payment.paidOn)
                                        : 'Sin fecha'}
                                </CashFigure>
                            </dl>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
