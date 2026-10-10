export type Expense = {
    id: number;
    /** The day it was paid on the salon's calendar, "2026-10-07". */
    date: string;
    /** Soles as text ("1200.50"). */
    amount: string;
    amountCents: number;
    method: string;
    methodLabel: string;
    /** Null when it has no category (or its category was removed). */
    categoryId: number | null;
    categoryName: string | null;
    description: string | null;
    receiptUrl: string | null;
};

export type ExpenseCategory = { id: number; name: string };
