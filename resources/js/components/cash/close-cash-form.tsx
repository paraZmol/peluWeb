import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AmountInput } from '@/components/accounting/amount-input';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { describeDifference, formatBalance, signedCents } from '@/lib/cash';
import { toCents } from '@/lib/money';
import { close } from '@/routes/cash';
import { CashDialogFooter } from './cash-dialog';
import { CashDifference } from './cash-difference';
import type { CashSession } from './types';

const MAX_NOTE = 255;

type Props = { session: CashSession };

/** The count against what the drawer should hold, compared while typing. */
export function CloseCashForm({ session }: Props) {
    const form = useForm({ amount: '', note: '' });
    const expectedCents = signedCents(session.expectedCash ?? '0');
    const countedCents = toCents(form.data.amount);
    const hasCount = form.data.amount.trim() !== '';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(close(session.id), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-5">
            <div className="flex flex-col gap-1 rounded-xl border bg-card p-4">
                <p className="flex items-baseline justify-between gap-4">
                    <span className="text-sm text-muted-foreground">
                        Efectivo esperado
                    </span>
                    <span className="text-xl font-semibold whitespace-nowrap tabular-nums">
                        {formatBalance(expectedCents)}
                    </span>
                </p>
                <p className="text-sm text-muted-foreground">
                    El fondo inicial más lo cobrado en efectivo, menos lo que
                    salió.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="cash-counted">Efectivo contado</Label>
                <AmountInput
                    id="cash-counted"
                    autoFocus
                    value={form.data.amount}
                    onChange={(amount) => form.setData('amount', amount)}
                    aria-invalid={Boolean(form.errors.amount)}
                    aria-describedby="cash-counted-result cash-counted-error"
                    className="h-12 text-lg"
                />
                <output
                    id="cash-counted-result"
                    htmlFor="cash-counted"
                    aria-live="polite"
                    className="min-h-5 text-sm"
                >
                    {!hasCount && (
                        <span className="text-muted-foreground">
                            Cuenta los billetes y monedas y escribe el total.
                        </span>
                    )}
                    {hasCount && countedCents === null && (
                        <span className="text-muted-foreground">
                            Usa números como 20 o 20.50.
                        </span>
                    )}
                    {hasCount && countedCents !== null && (
                        <CashDifference
                            difference={describeDifference(
                                countedCents - expectedCents,
                            )}
                        />
                    )}
                </output>
                <InputError
                    id="cash-counted-error"
                    message={form.errors.amount}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="cash-note">
                    Nota{' '}
                    <span className="font-normal text-muted-foreground">
                        (opcional)
                    </span>
                </Label>
                <Textarea
                    id="cash-note"
                    value={form.data.note}
                    maxLength={MAX_NOTE}
                    placeholder="Ej. Se dio vuelto de más a un cliente"
                    onChange={(e) => form.setData('note', e.target.value)}
                    aria-invalid={Boolean(form.errors.note)}
                    className="min-h-20"
                />
                <InputError message={form.errors.note} />
            </div>

            <CashDialogFooter
                confirmLabel="Cerrar caja"
                processing={form.processing}
            />
        </form>
    );
}
