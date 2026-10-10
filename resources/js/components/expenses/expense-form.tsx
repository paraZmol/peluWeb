import { AmountInput } from '@/components/accounting/amount-input';
import { PaymentMethodChips } from '@/components/accounting/payment-method-chips';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useExpenseForm } from '@/hooks/use-expense-form';
import type { Option } from '@/types';
import { CategorySelect } from './category-select';
import { ReceiptField } from './receipt-field';
import type { Expense, ExpenseCategory } from './types';

const MAX_DESCRIPTION = 160;

type Props = {
    /** The expense being edited; none when recording a new one. */
    expense?: Expense;
    /** Today on the salon's calendar: the default and the latest date. */
    today: string;
    categories: ExpenseCategory[];
    paymentMethods: Option[];
    onSaved: () => void;
};

/** Records or edits one expense. The server validates every field. */
export function ExpenseForm({
    expense,
    today,
    categories,
    paymentMethods,
    onSaved,
}: Props) {
    const form = useExpenseForm(expense, today, onSaved);
    const { data, setData, errors, processing } = form;

    return (
        <form onSubmit={form.submit} noValidate className="flex flex-col gap-5">
            <div className="grid gap-2">
                <Label htmlFor="expense-amount">Monto</Label>
                <AmountInput
                    id="expense-amount"
                    value={data.amount}
                    onChange={(amount) => setData('amount', amount)}
                    aria-invalid={Boolean(errors.amount)}
                    aria-describedby="expense-amount-error"
                    className="h-12 text-lg"
                />
                <InputError id="expense-amount-error" message={errors.amount} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="expense-category">Categoría</Label>
                    <CategorySelect
                        id="expense-category"
                        categories={categories}
                        value={data.category}
                        onChange={(category) => setData('category', category)}
                    />
                    <InputError message={errors.category} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="expense-date">Fecha</Label>
                    <Input
                        id="expense-date"
                        type="date"
                        max={today}
                        value={data.date}
                        onChange={(e) => setData('date', e.target.value)}
                        aria-invalid={Boolean(errors.date)}
                        className="h-11"
                    />
                    <InputError message={errors.date} />
                </div>
            </div>

            <div className="grid gap-2">
                <PaymentMethodChips
                    legend="¿Cómo se pagó?"
                    methods={paymentMethods}
                    value={data.method}
                    onChange={(method) => setData('method', method)}
                />
                <InputError message={errors.method} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="expense-description">
                    Descripción{' '}
                    <span className="font-normal text-muted-foreground">
                        (opcional)
                    </span>
                </Label>
                <Input
                    id="expense-description"
                    value={data.description}
                    maxLength={MAX_DESCRIPTION}
                    placeholder="Ej. Tintes y oxidantes"
                    onChange={(e) => setData('description', e.target.value)}
                    aria-invalid={Boolean(errors.description)}
                    className="h-11"
                />
                <InputError message={errors.description} />
            </div>

            <ReceiptField
                savedUrl={form.savedReceiptUrl}
                file={data.receipt}
                onPick={form.pickReceipt}
                onRemove={form.removeReceipt}
                error={errors.receipt}
            />

            <DialogFooter className="gap-2 [&_button]:h-12 sm:[&_button]:h-10">
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Volver
                    </Button>
                </DialogClose>
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {expense ? 'Guardar cambios' : 'Guardar gasto'}
                </Button>
            </DialogFooter>
        </form>
    );
}
