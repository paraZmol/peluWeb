import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { PaymentMethodChips } from '@/components/accounting/payment-method-chips';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    DialogClose,
    DialogFooter,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { CASH_METHOD } from '@/lib/cash';
import { countVisits, formatPeriod } from '@/lib/commissions';
import { formatPrice } from '@/lib/format';
import { settle } from '@/routes/commissions';
import type { Option } from '@/types';
import { CommissionDialog } from './commission-dialog';
import type { Period, StaffCommission } from './types';

type Props = {
    staff: StaffCommission;
    period: Period;
    paymentMethods: Option[];
};

/** Pays everything one professional is owed in the period on screen. */
export function SettleDialog({ staff, period, paymentMethods }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    // What is being paid stays while the dialog closes, when nothing is
    // owed anymore.
    const [paying, setPaying] = useState(staff);
    const price = formatPrice(staff.pending);
    const toggle = (open: boolean) => {
        if (open) {
            setPaying(staff);
        }

        setIsOpen(open);
    };

    return (
        <CommissionDialog
            open={isOpen}
            onOpenChange={toggle}
            trigger={
                <DialogTrigger asChild>
                    <Button
                        variant={
                            staff.pendingCents === 0 ? 'outline' : 'default'
                        }
                        disabled={staff.pendingCents === 0}
                        aria-label={`Liquidar ${price} a ${staff.name}`}
                        className="h-12 sm:h-10"
                    >
                        Liquidar {price}
                    </Button>
                </DialogTrigger>
            }
            title={`Liquidar comisiones de ${staff.name}`}
            description={`Pagas lo pendiente de ${countVisits(paying.visits)} del ${formatPeriod(period)}.`}
        >
            <SettleForm
                staff={paying}
                period={period}
                paymentMethods={paymentMethods}
                onSettled={() => setIsOpen(false)}
            />
        </CommissionDialog>
    );
}

function SettleForm({
    staff,
    period,
    paymentMethods,
    onSettled,
}: Props & { onSettled: () => void }) {
    const form = useForm({ ...period, method: CASH_METHOD });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(settle(staff.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: onSettled,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-5">
            <p className="flex items-baseline justify-between gap-4 rounded-xl border bg-card p-4">
                <span className="text-sm text-muted-foreground">
                    Total a pagar
                </span>
                <span className="text-3xl font-semibold whitespace-nowrap tabular-nums">
                    {formatPrice(staff.pending)}
                </span>
            </p>

            <div className="grid gap-2">
                <PaymentMethodChips
                    legend="¿Cómo le pagas?"
                    methods={paymentMethods}
                    value={form.data.method}
                    onChange={(method) => form.setData('method', method)}
                />
                <InputError message={form.errors.method} />
            </div>

            <p
                aria-live="polite"
                className="rounded-xl bg-muted p-3 text-sm text-muted-foreground"
            >
                Queda registrado como gasto en «Sueldos y comisiones»
                {form.data.method === CASH_METHOD
                    ? ' y, al ser en efectivo, sale de la caja.'
                    : '. No sale de la caja porque no es en efectivo.'}
            </p>

            <DialogFooter className="gap-2 [&_button]:h-12 sm:[&_button]:h-10">
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Volver
                    </Button>
                </DialogClose>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Sí, liquidar
                </Button>
            </DialogFooter>
        </form>
    );
}
