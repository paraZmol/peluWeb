import { Pencil, Trash2 } from 'lucide-react';
import { ConfirmRemoveDialog } from '@/components/team/confirm-remove-dialog';
import { Button } from '@/components/ui/button';
import { UNCATEGORIZED } from '@/lib/expenses';
import { formatDate, formatPrice } from '@/lib/format';
import { destroy } from '@/routes/expenses';
import { ReceiptViewer } from './receipt-viewer';
import type { Expense } from './types';

type Props = { expense: Expense; onEdit: (expense: Expense) => void };

/** One expense: what it was, how it was paid, how much, and its receipt. */
export function ExpenseRow({ expense, onEdit }: Props) {
    const category = expense.categoryName ?? UNCATEGORIZED;
    const price = formatPrice(expense.amount);
    const caption = `${category} · ${price} · ${formatDate(expense.date)}`;

    return (
        <li
            aria-label={caption}
            className="flex flex-col p-4 max-sm:pb-1 sm:flex-row sm:items-center sm:gap-4"
        >
            <div className="flex min-w-0 flex-1 items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-medium break-words">{category}</p>
                    <p className="text-sm break-words text-muted-foreground">
                        {[expense.description, expense.methodLabel]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                <p className="shrink-0 font-semibold tabular-nums">{price}</p>
            </div>
            <div className="flex items-center justify-end gap-1 max-sm:-mr-2">
                {expense.receiptUrl && (
                    <ReceiptViewer url={expense.receiptUrl} caption={caption} />
                )}
                {expense.isSettlement ? (
                    <p className="py-3 text-sm text-muted-foreground">
                        Pago de comisiones
                    </p>
                ) : (
                    <>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Editar gasto: ${caption}`}
                            onClick={() => onEdit(expense)}
                        >
                            <Pencil aria-hidden />
                        </Button>
                        <ConfirmRemoveDialog
                            triggerLabel="Eliminar"
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Eliminar gasto: ${caption}`}
                                    className="text-destructive-foreground"
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            }
                            title="¿Eliminar este gasto?"
                            description={`${caption}. Dejará de contar en el total del mes.`}
                            confirmLabel="Sí, eliminar"
                            themed
                            form={destroy.form(expense.id)}
                        />
                    </>
                )}
            </div>
        </li>
    );
}
