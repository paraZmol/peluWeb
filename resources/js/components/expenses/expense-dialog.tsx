import { usePortalTheme } from '@/components/salon/portal-theme';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SHEET_ON_PHONE } from '@/lib/sheet';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';
import { ExpenseForm } from './expense-form';
import type { Expense, ExpenseCategory } from './types';

type Props = {
    open: boolean;
    onClose: () => void;
    /** The expense being edited; none when recording a new one. */
    expense?: Expense;
    today: string;
    categories: ExpenseCategory[];
    paymentMethods: Option[];
};

/**
 * Records a new expense or edits one (a sheet on a phone). The form lives
 * only while open, so every opening starts clean.
 */
export function ExpenseDialog({ open, onClose, expense, ...form }: Props) {
    const portalTheme = usePortalTheme();
    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
            <DialogContent
                {...portalTheme}
                className={cn(
                    portalTheme.className,
                    SHEET_ON_PHONE,
                    'overflow-y-auto',
                )}
            >
                <DialogHeader className="pr-8 text-left">
                    <DialogTitle>
                        {expense ? 'Editar gasto' : 'Registrar gasto'}
                    </DialogTitle>
                    <DialogDescription>
                        {expense
                            ? 'Corrige lo que haga falta. Puedes cambiar o quitar la foto.'
                            : 'Lo que salió de la caja o de la cuenta del salón.'}
                    </DialogDescription>
                </DialogHeader>
                <ExpenseForm
                    key={expense?.id ?? 'new'}
                    expense={expense}
                    onSaved={onClose}
                    {...form}
                />
            </DialogContent>
        </Dialog>
    );
}
