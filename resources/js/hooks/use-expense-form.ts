import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import type { Expense } from '@/components/expenses/types';
import { store, update } from '@/routes/expenses';

/** How most small purchases are paid. */
const DEFAULT_METHOD = 'cash';

type Fields = {
    /** The category's id as text; empty for none. */
    category: string;
    amount: string;
    method: string;
    date: string;
    description: string;
    receipt: File | null;
    remove_receipt: boolean;
};

/**
 * What is being typed for one expense (a new one, or `expense` being
 * edited) and how it is sent. `today` is the salon's date, the default.
 */
export function useExpenseForm(
    expense: Expense | undefined,
    today: string,
    onSaved: () => void,
) {
    const form = useForm<Fields>({
        category: expense?.categoryId ? String(expense.categoryId) : '',
        amount: expense?.amount ?? '',
        method: expense?.method ?? DEFAULT_METHOD,
        date: expense?.date ?? today,
        description: expense?.description ?? '',
        receipt: null,
        remove_receipt: false,
    });
    const { data, setData } = form;

    return {
        data,
        setData,
        errors: form.errors,
        processing: form.processing,
        /** The photo already saved, unless it is being removed. */
        savedReceiptUrl: data.remove_receipt
            ? null
            : (expense?.receiptUrl ?? null),
        pickReceipt: (file: File) =>
            setData({ ...data, receipt: file, remove_receipt: false }),
        /** Only a saved photo needs the server to remove it. */
        removeReceipt: () =>
            setData({
                ...data,
                receipt: null,
                remove_receipt: Boolean(expense?.receiptUrl),
            }),
        // Always multipart: the same request with or without a photo.
        submit: (event: FormEvent) => {
            event.preventDefault();
            form.submit(expense ? update(expense.id) : store(), {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: onSaved,
            });
        },
    };
}
