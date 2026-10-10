import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AmountInput } from '@/components/accounting/amount-input';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { formatPrice } from '@/lib/format';
import { open } from '@/routes/cash';
import { CashDialogFooter } from './cash-dialog';

type Props = {
    /** What was counted at the last close, soles as text; none the first time. */
    lastCountedCash: string | null;
};

/** The float the day starts with. Zero is a valid answer. */
export function OpenCashForm({ lastCountedCash }: Props) {
    const form = useForm({ amount: lastCountedCash ?? '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(open(), { preserveScroll: true, preserveState: true });
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-5">
            <div className="grid gap-2">
                <Label htmlFor="cash-opening">Fondo inicial</Label>
                <AmountInput
                    id="cash-opening"
                    autoFocus
                    value={form.data.amount}
                    onChange={(amount) => form.setData('amount', amount)}
                    aria-invalid={Boolean(form.errors.amount)}
                    aria-describedby="cash-opening-hint cash-opening-error"
                    className="h-12 text-lg"
                />
                <p
                    id="cash-opening-hint"
                    className="text-sm text-muted-foreground"
                >
                    {lastCountedCash === null
                        ? 'El efectivo que hay en la caja antes de empezar. Si está vacía, escribe 0.'
                        : `En el último cierre contaste ${formatPrice(lastCountedCash)}. Cámbialo si retiraste o agregaste efectivo.`}
                </p>
                <InputError
                    id="cash-opening-error"
                    message={form.errors.amount}
                />
            </div>
            <CashDialogFooter
                confirmLabel="Abrir caja"
                processing={form.processing}
            />
        </form>
    );
}
