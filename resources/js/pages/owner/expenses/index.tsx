import { Head } from '@inertiajs/react';
import { Plus, ReceiptText } from 'lucide-react';
import { useState } from 'react';
import { CategoriesDialog } from '@/components/expenses/categories-dialog';
import { ExpenseDialog } from '@/components/expenses/expense-dialog';
import { ExpenseList } from '@/components/expenses/expense-list';
import { MonthPicker } from '@/components/expenses/month-picker';
import { MonthSummary } from '@/components/expenses/month-summary';
import type { Expense, ExpenseCategory } from '@/components/expenses/types';
import { Button } from '@/components/ui/button';
import { formatMonth } from '@/lib/format';
import { index } from '@/routes/expenses';
import type { Option } from '@/types';

type Props = {
    /** The month on screen, "2026-10". */
    month: string;
    currentMonth: string;
    /** Today on the salon's calendar, "2026-10-07". */
    today: string;
    expenses: Expense[];
    /** The month's total in soles ("1200.50"). */
    total: string;
    categories: ExpenseCategory[];
    paymentMethods: Option[];
};

/** Closed, recording a new expense, or editing one. */
type DialogState = { isOpen: boolean; expense?: Expense };

export default function Expenses({
    month,
    currentMonth,
    today,
    expenses,
    total,
    categories,
    paymentMethods,
}: Props) {
    // The expense stays while the dialog closes, so its title does not flip.
    const [dialog, setDialog] = useState<DialogState>({ isOpen: false });
    const isEmpty = expenses.length === 0;

    return (
        <>
            <Head title="Gastos" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Gastos</h1>
                        <p className="text-sm text-muted-foreground">
                            Lo que gasta tu salón, mes a mes, con la foto de
                            cada comprobante.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <CategoriesDialog categories={categories} />
                        <Button onClick={() => setDialog({ isOpen: true })}>
                            <Plus aria-hidden /> Registrar gasto
                        </Button>
                    </div>
                </div>

                <MonthPicker month={month} currentMonth={currentMonth} />

                <div className="grid items-start gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
                    <div className="lg:sticky lg:top-6">
                        <MonthSummary
                            month={month}
                            total={total}
                            expenses={expenses}
                        />
                    </div>

                    {isEmpty ? (
                        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center">
                            <ReceiptText
                                className="size-6 text-muted-foreground"
                                aria-hidden
                            />
                            <p className="text-muted-foreground">
                                No hay gastos en{' '}
                                {formatMonth(month).toLowerCase()}. Anota el
                                alquiler, los insumos o la luz para saber cuánto
                                te queda.
                            </p>
                            <Button onClick={() => setDialog({ isOpen: true })}>
                                <Plus aria-hidden /> Registrar el primer gasto
                            </Button>
                        </div>
                    ) : (
                        <ExpenseList
                            expenses={expenses}
                            onEdit={(expense) =>
                                setDialog({ isOpen: true, expense })
                            }
                        />
                    )}
                </div>
            </div>

            <ExpenseDialog
                open={dialog.isOpen}
                onClose={() => setDialog({ ...dialog, isOpen: false })}
                expense={dialog.expense}
                today={today}
                categories={categories}
                paymentMethods={paymentMethods}
            />
        </>
    );
}

Expenses.layout = {
    breadcrumbs: [{ title: 'Gastos', href: index() }],
};
